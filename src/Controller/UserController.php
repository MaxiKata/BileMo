<?php


namespace App\Controller;


use App\Entity\Client;
use App\Entity\User;
use App\Repository\AccessTokenRepository;
use App\Repository\ClientRepository;
use App\Repository\RefreshTokenRepository;
use App\Repository\UserRepository;
use App\Representation\Users;
use Doctrine\ORM\EntityManagerInterface;
use FOS\OAuthServerBundle\Controller\TokenController;
use FOS\OAuthServerBundle\Model\ClientManagerInterface;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Request\ParamFetcherInterface;
use FOS\RestBundle\View\View;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use Nelmio\ApiDocBundle\Annotation\Security;
use Swagger\Annotations as SWG;

/**
 * Class UserController
 * @package App\Controller
 */
class UserController extends AbstractFOSRestController
{

    private $em;
    private $repository;
    private $encoder;
    private $clientRepository;
    private $tokenController;
    private $clientManager;
    private $accessTokenRepository;
    private $refreshTokenRepository;

    /**
     * UserController constructor.
     * @param EntityManagerInterface $em
     * @param UserRepository $repository
     * @param UserPasswordEncoderInterface $encoder
     * @param ClientRepository $clientRepository
     * @param TokenController $tokenController
     * @param ClientManagerInterface $clientManager
     * @param AccessTokenRepository $accessTokenRepository
     * @param RefreshTokenRepository $refreshTokenRepository
     */
    public function __construct(
        EntityManagerInterface $em,
        UserRepository $repository,
        UserPasswordEncoderInterface $encoder,
        ClientRepository $clientRepository,
        TokenController $tokenController,
        ClientManagerInterface $clientManager,
        AccessTokenRepository $accessTokenRepository,
        RefreshTokenRepository $refreshTokenRepository
    )
    {
        $this->em = $em;
        $this->repository = $repository;
        $this->encoder = $encoder;
        $this->clientRepository = $clientRepository;
        $this->tokenController = $tokenController;
        $this->clientManager = $clientManager;
        $this->accessTokenRepository = $accessTokenRepository;
        $this->refreshTokenRepository = $refreshTokenRepository;
    }

    /**
     * @Rest\Get(
     *     path="user/{id}",
     *     name="get_user",
     *     requirements={"id"="\d+"}
     * )
     * @Rest\View()
     * @SWG\Response(
     *     response=200,
     *     description="Showing User profile from the same Client",
     *     @SWG\Schema(
     *          @SWG\Items(ref=@Model(type=User::class))
     *     )
     * )
     * @SWG\Response(
     *     response=403,
     *     description="You are trying to access to a User with a different Client which is not the same as the user - So no credentials",
     * )
     * @SWG\Parameter(
     *     name="id",
     *     in="path",
     *     type="integer",
     *     description="The ID of the User"
     * )
     * @SWG\Tag(name="User")
     * @Security(name="Bearer")
     * @param User $user
     * @param Request $request
     * @return User
     */
    public function getUserAction(User $user, Request $request){
        $token = filter_var($request->headers->get('X-AUTH-TOKEN'), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $result = $this->accessTokenRepository->findOneBy(['token' => $token]);
        if($result && $result->getClient() === $user->getClient() || $result->getUser()->getRoles() == 'ROLE_ADMIN'){
            return $user;
        }
        throw new HttpException(Response::HTTP_FORBIDDEN,'You are not allowed to see this User');
    }

    /**
     * @Rest\Delete(
     *     path="user/{id}",
     *     name="delete_user",
     *     requirements={"id"="\d+"}
     * )
     * @SWG\Response(
     *     response=204,
     *     description="Profile of the User has been well deleted"
     * )
     * @SWG\Response(
     *     response=403,
     *     description="User can only delete is own profile - So no credentials",
     * )
     * @SWG\Parameter(
     *     name="id",
     *     in="path",
     *     type="integer",
     *     description="The ID of the User"
     * )
     * @SWG\Tag(name="User")
     * @Security(name="Bearer")
     * @param User $user
     * @param Request $request
     * @return View
     */
    public function deleteUserAction(User $user, Request $request){
        $token = filter_var($request->headers->get('X-AUTH-TOKEN'), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $result = $this->accessTokenRepository->findOneBy(['token' => $token]);

        if($result && $result->getUser() === $user || $result->getUser()->getRoles() == 'ROLE_ADMIN'){
            $this->em->remove($user);
            $this->em->flush();
            return $this->view('null', Response::HTTP_NO_CONTENT);
        }
        throw new HttpException(Response::HTTP_FORBIDDEN, 'You can only delete your own account');
    }

    /**
     * @Rest\Get(
     *     path="/users/{id}",
     *     name="users_list",
     *     requirements={"id"="\d+"}
     * )
     * @Rest\QueryParam(
     *     name="keyword",
     *     requirements="[a-zA-Z0-9]",
     *     default="{id}",
     *     nullable=true,
     *     description="The keyword to search for."
     * )
     * @Rest\QueryParam(
     *     name="order",
     *     requirements="asc|desc",
     *     default="asc",
     *     description="Sort order (asc or desc)"
     * )
     * @Rest\QueryParam(
     *     name="limit",
     *     requirements="\d+",
     *     default="15",
     *     description="Max number of users per page."
     * )
     * @Rest\QueryParam(
     *     name="offset",
     *     requirements="\d+",
     *     default="1",
     *     description="The pagination offset"
     * )
     * @Rest\View()
     * @SWG\Response(
     *     response=200,
     *     description="The list of all the Users of the same Client",
     *     @SWG\Schema(
     *          @SWG\Items(ref=@Model(type=Users::class))
     *     )
     * )
     * @SWG\Response(
     *     response=403,
     *     description="You are trying to access to a Client which is not the same as the User - So you dont have the credentials",
     * )
     * @SWG\Parameter(
     *     name="id",
     *     in="path",
     *     type="integer",
     *     description="The ID of the Client"
     * )
     * @SWG\Tag(name="User")
     * @Security(name="Bearer")
     * @param Client $client
     * @param Request $request
     * @param ParamFetcherInterface $paramFetcher
     * @return Users
     */
    public function getUsersAction(Client $client, Request $request, ParamFetcherInterface $paramFetcher)
    {
        $token = filter_var($request->headers->get('X-AUTH-TOKEN'), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $result = $this->accessTokenRepository->findOneBy(['token' => $token]);
        if($result && $result->getClient() == $client || $result->getUser()->getRoles() == 'ROLE_ADMIN'){
            $pager = $this->repository->search(
                $client,
                $paramFetcher->get('order'),
                $paramFetcher->get('limit'),
                $paramFetcher->get('offset')
            );
            return new Users($pager);
        }
        throw new HttpException(Response::HTTP_FORBIDDEN,'You don\'t have the credentials to access to this client');
    }

    /**
     * @Rest\Post(
     *     path="/login",
     *     name="login_user"
     * )
     * @Rest\View()
     * @SWG\Parameter(
     *     name="username",
     *     in="body",
     *     type="string",
     *     description="Username of the User",
     *     required=true,
     *     @SWG\Schema(
     *          @SWG\Property(property="username", type="string")
     *     )
     * )
     * @SWG\Parameter(
     *     name="email",
     *     in="body",
     *     type="string",
     *     description="Email of the User",
     *     required=true,
     *     @SWG\Schema(
     *          @SWG\Property(property="email", type="string")
     *     )
     * )
     * @SWG\Parameter(
     *     name="password",
     *     in="body",
     *     type="string",
     *     description="Password of the User",
     *     required=true,
     *     @SWG\Schema(
     *          @SWG\Property(property="password", type="string")
     *     )
     * )
     * @SWG\Parameter(
     *     name="client_id",
     *     in="body",
     *     type="string",
     *     description="Client Id of the User",
     *     required=true,
     *     @SWG\Schema(
     *          @SWG\Property(property="client_id", type="string")
     *     )
     * )
     * @SWG\Parameter(
     *     name="client_secret",
     *     in="body",
     *     type="string",
     *     description="Client Secret of the User",
     *     required=true,
     *     @SWG\Schema(
     *          @SWG\Property(property="client_secret", type="string")
     *     )
     * )
     * @SWG\Parameter(
     *     name="clientName",
     *     in="body",
     *     type="string",
     *     description="Client Name of the User",
     *     @SWG\Schema(
     *          @SWG\Property(property="clientName", type="string")
     *     )
     * )
     * @SWG\Response(
     *     response=200,
     *     description="Send back the token that has been created to connect on BileMo website",
     *     @SWG\Schema(
     *          @SWG\Property(
     *              property="access_token",
     *              type="string"
     *          ),
     *          @SWG\Property(
     *              property="expires_in",
     *              type="integer"
     *          ),
     *          @SWG\Property(
     *              property="token_type",
     *              type="string"
     *          ),
     *          @SWG\Property(
     *              property="scope",
     *              type="string"
     *          ),
     *          @SWG\Property(
     *              property="refresh_token",
     *              type="string"
     *          )
     *     )
     * )
     * @SWG\Response(
     *     response=400,
     *     description="Bad request - Authentification is incorrect",
     * )
     * @SWG\Response(
     *     response=422,
     *     description="Missing fields or empty",
     * )
     * @SWG\Tag(name="User")
     * @param Request $request
     * @return Response
     */
    public function registerAction(Request $request)
    {
        $data = json_decode($request->getContent(), true);
        $token = null;

        if(empty($data['username']) || !$data['username']){
            throw new HttpException(Response::HTTP_BAD_REQUEST,'Username missing');
        }elseif (empty($data['email']) || !$data['email']){
            throw new HttpException(Response::HTTP_BAD_REQUEST,'Email missing');
        }elseif (empty($data['password']) || !$data['password']){
            throw new HttpException(Response::HTTP_BAD_REQUEST,'Password missing');
        }elseif (empty($data['client_id']) || !isset($data['client_id']) || empty($data['client_secret']) || !isset($data['client_secret'])){
            if (empty($data['clientName']) || !isset($data['clientName'])){
                throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, 'client_id, client_secret or clientName missing');
            }
        }
        if (isset($data['client_id']) && isset($data['client_secret'])){
            $client = $this->clientManager->findClientByPublicId($data['client_id']);
            if($client->getSecret() != $data['client_secret']){
                throw new HttpException(Response::HTTP_BAD_REQUEST, 'Client identification incorrect');
            }
        }elseif($data['clientName']){
            $client = $this->clientRepository->findOneBy(['name' => $data['clientName']]);
            if(empty($client)){
                throw new HttpException(Response::HTTP_BAD_REQUEST, 'Client identification incorrect');
            }
        }

        $result = $this->register(
            $data['username'],
            $data['email'],
            $data['password'],
            $client,
            $token
        );

        if($result){
            if(is_string($result)){
                $result = $this->clearToken($result);
                if($result){
                    return $result;
                }
            }
            $request = Request::create(
                json_encode($request->query->all()),
                'POST',
                ['Content-Type'     =>  'application/json',
                    'client_id'     =>  $client->getPublicId(),
                    'client_secret' =>  $client->getSecret(),
                    'grant_type'    =>  'password',
                    'username'      =>  $data['username'],
                    'password'      =>  $data['password'],
                    'scope'         =>  'user'
                ],
                $request->cookies->all(),
                $request->files->all(),
                $request->server->all(),
                ''
            );
            $view = $this->tokenController->tokenAction($request);
            $token = $this->updateToken($view);

            $this->register(
                $data['username'],
                $data['email'],
                $data['password'],
                $client,
                $token
            );
            return $view;
        }

        throw new HttpException(Response::HTTP_BAD_REQUEST,'User already exits or password is wrong');
    }

    /**
     * @param $username
     * @param $email
     * @param $password
     * @param $client
     * @param $token
     * @return bool
     */
    private function register($username, $email, $password, $client, $token)
    {
        $email_exist = $this->repository->findOneBy(['email' => $email]);
        $username_exist = $this->repository->findOneBy(['email' => $username]);
        $checkUser = $this->repository->findOneBy(['client' =>$client, 'username'=> $username, 'email' => $email]);
        if($checkUser){
            if(!$this->encoder->isPasswordValid($checkUser, $password)){
                return false;
            }elseif ($token){
                $this->updateUser($checkUser, $token);
            }
            return $checkUser->getConfirmationToken();
        }
        if($email_exist){
            if(!$this->encoder->isPasswordValid($email_exist, $password)){
                return false;
            }
            $email_exist->setClient($client);
            $this->updateUser($email_exist, $token);
            return $email_exist->getConfirmationToken();
        }elseif ($username_exist){
            if(!$this->encoder->isPasswordValid($username_exist, $password)){
                return false;
            }
            $username_exist->setClient($client);
            $this->updateUser($username_exist, $token);
            return $username_exist->getConfirmationToken();
        }

        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setEmailCanonical($email);
        $user->setEnabled(1);
        $user->setPassword($this->encoder->encodePassword($user, $password));
        $user->addRole("ROLE_USER");
        $user->setClient($client);
        $this->updateUser($user, $token);

        return true;
    }

    /**
     * @param $view
     * @return mixed
     */
    private function updateToken($view)
    {
        $finalArray= [];
        $viewContent = str_replace('"','', $view->getContent());
        $viewContent = ltrim($viewContent, '{');
        $viewContent = rtrim($viewContent, '}');

        $asArr = explode(',', $viewContent);
        foreach ($asArr as $value){
            $tmp = explode(':', $value);
            $finalArray[ $tmp[0] ] = $tmp[1];
        }
        return $finalArray['access_token'];
    }

    /**
     * @param User $user
     * @param $token
     */
    private function updateUser(User $user, $token)
    {
        $user->setConfirmationToken($token);
        $this->em->persist($user);
        $this->em->flush();
    }

    /**
     * @param $result
     * @return JsonResponse
     */
    private function clearToken($result)
    {
        $accessToken = $this->accessTokenRepository->findOneBy(['token' => $result]);
        $refreshToken = $this->refreshTokenRepository->findOneBy([
            'id' => $accessToken->getId(),
            'user' => $accessToken->getUser()
        ]);
        if ($accessToken->getExpiresAt() >= time()){
            return new JsonResponse([
                'access_token'  =>  $accessToken->getToken(),
                'expires_in'    =>  $accessToken->getExpiresIn(),
                'token_type'    =>  'bearer',
                'scope'         =>  $accessToken->getScope(),
                'refresh_token' =>  $refreshToken->getToken()
            ]);
        }
        $this->em->remove($accessToken);
        $this->em->remove($refreshToken);
        $this->em->flush();
        return false;
    }
}