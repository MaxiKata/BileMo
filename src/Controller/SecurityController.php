<?php


namespace App\Controller;

use App\Exception\ResourceValidationException;
use FOS\OAuthServerBundle\Model\ClientManagerInterface;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityController extends AbstractFOSRestController
{
    private $client_manager;
    public function __construct(ClientManagerInterface $client_manager)
    {
        $this->client_manager = $client_manager;
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
}