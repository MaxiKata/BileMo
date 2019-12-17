<?php


namespace App\Controller;

use App\Exception\ResourceValidationException;
use App\Repository\ClientRepository;
use Doctrine\ORM\EntityManagerInterface;
use FOS\OAuthServerBundle\Model\ClientManagerInterface;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\View\View;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityController extends AbstractFOSRestController
{
    private $client_manager;
    private $repository;
    private $em;

    public function __construct(ClientManagerInterface $client_manager, ClientRepository $repository, EntityManagerInterface $em)
    {
        $this->client_manager = $client_manager;
        $this->repository = $repository;
        $this->em = $em;
    }

    /**
     * Create Client.
     * @Rest\Post("/createClient")
     *
     * @param Request $request
     * @return Response
     * @throws ResourceValidationException
     */
    public function AuthenticationAction(Request $request)
    {
        $data = json_decode($request->getContent(), true);
        if (empty($data['redirect-uri']) || empty($data['grant-type']) || empty($data['name'])) {
            $message = "Some datas are missing : redirect-uri or grant-type or name. Your datas are:<br>" . json_encode($data);
            throw new ResourceValidationException($message, 403);
        }
        $clientManager = $this->client_manager;
        $client = $clientManager->createClient();
        $client->setRedirectUris([$data['redirect-uri']]);
        $client->setAllowedGrantTypes([$data['grant-type']]);
        $client->setName($data['name']); // setName method Must be add to Client Manager Interface
        $clientManager->updateClient($client);
        $rows = [
            'client_id' => $client->getPublicId(), 'client_secret' => $client->getSecret()
        ];
        return $this->handleView($this->view($rows));
    }

    /**
     * Delete Client
     * @Rest\Delete(
     *     path="/deleteClient",
     *     name="delete_client"
     * )
     * @param Request $request
     * @return View
     * @throws ResourceValidationException
     */
    public function RemoveAction(Request $request)
    {
        $data = json_encode($request->getContent(), true);
        $header = $request->headers->all();

        if (!isset($data['client-id']) || !isset($data['client-secret'])) {
            if(!isset($header['client-id']) || !isset($header['client-secret'])){
                $message = "Some data or headers are missing : client id or client secret";
                throw new ResourceValidationException($message, 403);
            }elseif (empty($header['client-id']) || empty($header['client-secret'])){
                $message = "Some headers value are empty : client id or client secret";
                throw new ResourceValidationException($message, 403);
            }
            $clientId = str_replace(['["', '"]'], ['', ''], json_encode($header['client-id']));
            sscanf($clientId, '%d_%s', $id, $randomId);
            $filters = ['id' => $id, 'randomId' => $randomId, 'secret' => $header['client-secret']];
        }elseif (empty($data['client-id']) || empty($data['client-secret'])){
            $message = "Some data value are empty : client id or client secret";
            throw new ResourceValidationException($message, 403);
        }else{
            $clientId = str_replace(['["', '"]'], ['', ''], json_encode($data['client-id']));
            sscanf($clientId, "%d_%s", $id, $randomId);
            $filters = ['id' => $id, 'randomId' => $randomId, 'secret' => $data['client-secret']];
        }

        $client = $this->repository->findOneBy($filters);
        if($client){
            $this->em->remove($client);
            $this->em->flush();
            return $this->view('null', Response::HTTP_NO_CONTENT);
        }
        throw new ResourceValidationException('One of your data must be wrong, client not found', 404);
    }
}