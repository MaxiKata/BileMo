<?php


namespace App\Controller;


use App\Entity\User;
use App\Exception\ResourceValidationException;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class UserController extends AbstractFOSRestController
{
    private $em;
    private $repository;
    private $encoder;

    public function __construct(EntityManagerInterface $em, UserRepository $repository, UserPasswordEncoderInterface $encoder)
    {
        $this->em = $em;
        $this->repository = $repository;
        $this->encoder = $encoder;
    }

    /**
     * @Rest\Post(
     *     path="/login",
     *     name="login_user"
     * )
     *
     * @Rest\View()
     * @param Request $request
     * @throws ResourceValidationException
     */
    public function registerAction(Request $request)
    {
        $data = json_decode($request->getContent(), true);

        if(empty($data['username']) || !$data['username']){
            throw new ResourceValidationException('Username missing', 303);
        }elseif (empty($data['email']) || !$data['email']){
            throw new ResourceValidationException('Email missing', 303);
        }elseif (empty($data['password']) || !$data['password']){
            throw new ResourceValidationException('Password missing', 303);
        }

        $result = $this->register(
            $data['username'],
            $data['email'],
            $data['password']
        );
        if($result){
            throw new ResourceValidationException('User has been well created', 201);
        }else{
            throw new ResourceValidationException('User Already exists', 403);
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

        if($email_exist || $username_exist){
            return false;
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