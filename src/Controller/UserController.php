<?php


namespace App\Controller;


use App\Entity\Client;
use App\Entity\User;
use App\Exception\ResourceValidationException;
use App\Repository\AccessTokenRepository;
use App\Repository\ClientRepository;
use App\Repository\UserRepository;
use App\Representation\Users;
use Doctrine\ORM\EntityManagerInterface;
use FOS\OAuthServerBundle\Controller\TokenController;
use FOS\OAuthServerBundle\Model\ClientManagerInterface;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Request\ParamFetcherInterface;
use FOS\RestBundle\View\View;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class UserController extends AbstractFOSRestController
{
    private $em;
    private $repository;
    private $encoder;
    private $clientRepository;
    private $tokenController;
    private $clientManager;
    private $accessTokenRepository;

    public function __construct(
        EntityManagerInterface $em,
        UserRepository $repository,
        UserPasswordEncoderInterface $encoder,
        ClientRepository $clientRepository,
        TokenController $tokenController,
        ClientManagerInterface $clientManager,
        AccessTokenRepository $accessTokenRepository
    )
    {
        $this->em = $em;
        $this->repository = $repository;
        $this->encoder = $encoder;
        $this->clientRepository = $clientRepository;
        $this->tokenController = $tokenController;
        $this->clientManager = $clientManager;
        $this->accessTokenRepository = $accessTokenRepository;
    }

    /**
     * @Rest\Get(
     *     path="user/{id}",
     *     name="get_user",
     *     requirements={"id"="\d+"}
     * )
     * @Rest\View()
     * @param User $user
     * @param Request $request
     * @return User
     * @throws ResourceValidationException
     */
    public function getUserAction(User $user, Request $request){
        $token = filter_var($request->headers->get('X-AUTH-TOKEN'), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $result = $this->accessTokenRepository->findOneBy(['token' => $token]);
        if($result && $result->getClient() === $user->getClient()){
            return $user;
        }
        throw new ResourceValidationException('You are not allowed to see this User');
    }

    /**
     * @Rest\Delete(
     *     path="user/{id}",
     *     name="delte_user",
     *     requirements={"id"="\d+"}
     * )
     * @param User $user
     * @param Request $request
     * @return View
     * @throws ResourceValidationException
     */
    public function deleteUserAction(User $user, Request $request){
        $token = filter_var($request->headers->get('X-AUTH-TOKEN'), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $result = $this->accessTokenRepository->findOneBy(['token' => $token]);
        if($result && $result->getUser() === $user){
            $this->em->remove($user);
            $this->em->flush();
            return $this->view('null', Response::HTTP_NO_CONTENT);
        }
        throw new ResourceValidationException('You can only delete your own account');
    }

    /**
     * @Rest\Post(
     *     path="/login",
     *     name="login_user"
     * )
     *
     * @Rest\View()
     * @param Request $request
     * @return Response
     * @throws ResourceValidationException
     */
    public function registerAction(Request $request)
    {
        $data = json_decode($request->getContent(), true);

        if(empty($data['username']) || !$data['username']){
            throw new ResourceValidationException('username missing', 303);
        }elseif (empty($data['email']) || !$data['email']){
            throw new ResourceValidationException('email missing', 303);
        }elseif (empty($data['password']) || !$data['password']){
            throw new ResourceValidationException('password missing', 303);
        }elseif (empty($data['client_id']) || !isset($data['client_id']) || empty($data['client_secret']) || !isset($data['client_secret'])){
            if (empty($data['clientName']) || !isset($data['clientName'])){
                throw new ResourceValidationException('client_id, client_secret or clientName missing', 303);
            }
        }
        if (isset($data['client_id']) && isset($data['client_secret'])){
            $client = $this->clientManager->findClientByPublicId($data['client_id']);
            if($client->getSecret() != $data['client_secret']){
                throw new ResourceValidationException('Client identification incorrect');
            }
        }elseif($data['clientName']){
            $client = $this->clientRepository->findOneBy(['name' => $data['clientName']]);
        }

        $result = $this->register(
            $data['username'],
            $data['email'],
            $data['password'],
            $client
        );
        if($result){
            $request = Request::create(
                json_encode($request->query->all()),
                'POST',
                ['Content-Type' => 'application/json',
                    'client_id' => $client->getPublicId(),
                    'client_secret'=>$client->getSecret(),
                    'grant_type' => 'password',
                    'username' => $data['username'],
                    'password' => $data['password'],
                    'scope' => 'ROLE_USER'
                ],
                $request->cookies->all(),
                $request->files->all(),
                $request->server->all(),
                ''
            );
            return $this->tokenController->tokenAction($request);
        }else{
            throw new ResourceValidationException('User already exits or password is wrong');
        }
    }

    /**
     * @Rest\Get(
     *     path="/users/{id}",
     *     name="users_list",
     *     requirements={"id"="\d+"}
     * )
     *
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
     *     description="Max number of movies per page."
     * )
     * @Rest\QueryParam(
     *     name="offset",
     *     requirements="\d+",
     *     default="1",
     *     description="The pagination offset"
     * )
     * @Rest\View()
     * @param Client $client
     * @param Request $request
     * @param ParamFetcherInterface $paramFetcher
     * @return Users
     * @throws ResourceValidationException
     */
    public function getUsersAction(Client $client, Request $request, ParamFetcherInterface $paramFetcher)
    {
        $token = filter_var($request->headers->get('X-AUTH-TOKEN'), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $result = $this->accessTokenRepository->findOneBy(['token' => $token]);
        if($result && $result->getClient() == $client){
            $pager = $this->repository->search(
                $client,
                $paramFetcher->get('order'),
                $paramFetcher->get('limit'),
                $paramFetcher->get('offset')
            );
            return new Users($pager);
        }
        throw new ResourceValidationException('You don\'t have the credentials to access to this client');
    }

    /**
     * @param $username
     * @param $email
     * @param $password
     * @param $client
     * @return bool
     */
    private function register($username, $email, $password, $client)
    {
        $email_exist = $this->repository->findOneBy(['email' => $email]);
        $username_exist = $this->repository->findOneBy(['email' => $username]);
        $checkUser = $this->repository->findOneBy(['client' =>$client, 'username'=> $username, 'email' => $email]);
        if($checkUser){
            if(!$this->encoder->isPasswordValid($checkUser, $password)){
                return false;
            }
            return true;
        }
        if($email_exist){
            if(!$this->encoder->isPasswordValid($email_exist, $password)){
                return false;
            }
            $email_exist->setClient($client);
            $this->em->persist($email_exist);
            $this->em->flush();
            return true;
        }elseif ($username_exist){
            if(!$this->encoder->isPasswordValid($username_exist, $password)){
                return false;
            }
            $username_exist->setClient($client);
            $this->em->persist($username_exist);
            $this->em->flush();
            return true;
        }

        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setEmailCanonical($email);
        $user->setEnabled(1);
        $user->setPassword($this->encoder->encodePassword($user, $password));
        $user->addRole("ROLE_ADMIN");
        $user->setClient($client);

        $this->em->persist($user);
        $this->em->flush();

        return true;
    }
}