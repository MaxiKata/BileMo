<?php


namespace App\Controller;


use App\Entity\User;
use App\Exception\ResourceValidationException;
use App\Repository\ClientRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use FOS\OAuthServerBundle\Controller\TokenController;
use FOS\OAuthServerBundle\Model\ClientManagerInterface;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
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

    public function __construct(EntityManagerInterface $em, UserRepository $repository, UserPasswordEncoderInterface $encoder, ClientRepository $clientRepository, TokenController $tokenController, ClientManagerInterface $clientManager)
    {
        $this->em = $em;
        $this->repository = $repository;
        $this->encoder = $encoder;
        $this->clientRepository = $clientRepository;
        $this->tokenController = $tokenController;
        $this->clientManager = $clientManager;
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
        $result = $this->register(
            $data['username'],
            $data['email'],
            $data['password']
        );
        if($result){

            if (isset($data['client_id']) && isset($data['client_secret'])){
                $client = $this->clientManager->findClientByPublicId($data['client_id']);
                if($client->getSecret() != $data['client_secret']){
                    throw new ResourceValidationException('Client identification incorect');
                }
            }elseif($data['clientName']){
                $client = $this->clientRepository->findOneBy(['name' => $data['clientName']]);
            }
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
            throw new ResourceValidationException('User already exits but password is wrong');
        }
    }

    /**
     * @param $username
     * @param $email
     * @param $password
     * @return bool
     */
    private function register($username, $email, $password)
    {
        $email_exist = $this->repository->findOneBy(['email' => $email]);
        $username_exist = $this->repository->findOneBy(['email' => $username]);

        if($email_exist){
            if(!$this->encoder->isPasswordValid($email_exist, $password)){
                return false;
            }
            return true;
        }elseif ($username_exist){
            if(!$this->encoder->isPasswordValid($username_exist, $password)){
                return false;
            }
            return true;
        }

        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setEmailCanonical($email);
        $user->setEnabled(1);
        $user->setPassword($this->encoder->encodePassword($user, $password));
        $user->addRole("ROLE_ADMIN");

        $this->em->persist($user);
        $this->em->flush();

        return true;
    }
}