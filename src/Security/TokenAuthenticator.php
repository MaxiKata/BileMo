<?php


namespace App\Security;


use App\Entity\AccessToken;
use App\Repository\AccessTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;

class TokenAuthenticator extends AbstractGuardAuthenticator
{
    private $em;

    private $repository;

    public function __construct(EntityManagerInterface $em, AccessTokenRepository $repository)
    {
        $this->em = $em;
        $this->repository = $repository;
    }

    /**
     * @inheritDoc
     * Called when authentication is needed, but it is not sent
     */
    public function start(Request $request, AuthenticationException $authException = null)
    {
        $data = [
            'message' => 'Authorization Required'
        ];
        return new JsonResponse($data, Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Called on every request to decide if authenticator should be used for the request. Returning false will skip this.
     * @Rest\Post(
     *     path="/oauth/v2/auth_login",
     *     name="login"
     * )
     * @inheritDoc
     */
    public function supports(Request $request)
    {
        return $request->headers->has('X-AUTH-TOKEN');
    }

    /**
     * @inheritDoc
     */
    public function getCredentials(Request $request)
    {
        return [
            'token' => $request->headers->get('X-AUTH-TOKEN'),
        ];
    }

    /**
     * @inheritDoc
     */
    public function getUser($credentials, UserProviderInterface $userProvider)
    {
        $token = $credentials['token'];

        if (null === $token) {
            return;
        }

        if(null === $this->em->getRepository(AccessToken::class)->findOneBy(['token' => $token])){
            throw new HttpException(Response::HTTP_BAD_REQUEST,'Credential token is not valid');
        }

        if($this->em->getRepository(AccessToken::class)
            ->findOneBy(['token' => $token])->getExpiresAt() <= time()){
            throw new HttpException(Response::HTTP_FORBIDDEN, 'Token Expired, please login again');
        }

        return $this->em->getRepository(AccessToken::class)
            ->findOneBy(['token' => $token])->getUser();
    }

    /**
     * @inheritDoc
     */
    public function checkCredentials($credentials, UserInterface $user)
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
    {
        $data = [
            'message' => strtr($exception->getMessageKey(), $exception->getMessageData())
        ];

        return new JsonResponse($data, Response::HTTP_FORBIDDEN);
    }

    /**
     * @inheritDoc
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey)
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function supportsRememberMe()
    {
        return false;
    }
}