<?php


namespace App\Controller;

use App\Entity\Phone;
use App\Repository\PhoneRepository;
use App\Representation\Phones;
use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Request\ParamFetcherInterface;
use FOS\RestBundle\View\View;
use JMS\Serializer\SerializerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Nelmio\ApiDocBundle\Annotation\Model;
use Nelmio\ApiDocBundle\Annotation\Security;
use Swagger\Annotations as SWG;

/**
 * Class PhoneController
 * @package App\Controller
 */
class PhoneController extends AbstractFOSRestController
{
    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var PhoneRepository
     */
    private $repository;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    /**
     * PhoneController constructor.
     * @param EntityManagerInterface $em
     * @param PhoneRepository $repository
     * @param SerializerInterface $serializer
     */
    public function __construct(EntityManagerInterface $em, PhoneRepository $repository, SerializerInterface $serializer)
    {
        $this->em = $em;
        $this->repository = $repository;
        $this->serializer = $serializer;
    }

    /**
     * @Rest\Get(
     *     path="/phones",
     *     name="phones_list"
     * )
     * @Rest\QueryParam(
     *     name="keyword",
     *     requirements="[a-zA-Z0-9]",
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
     *     description="Max number of phones per page."
     * )
     * @Rest\QueryParam(
     *     name="offset",
     *     requirements="\d+",
     *     default="1",
     *     description="The pagination offset"
     * )
     * @SWG\Response(
     *     response=200,
     *     description="The list of all the Phones proposed by BileMo",
     *     @SWG\Schema(
     *          @SWG\Items(ref=@Model(type=Phones::class))
     *     )
     * )
     * @SWG\Response(
     *     response=204,
     *     description="The list of all the Phones is empty"
     * )
     * @SWG\Tag(name="Phone")
     * @Security(name="Bearer")
     * @Rest\View()
     * @param ParamFetcherInterface $paramFetcher
     * @return Phones | View
     */
    public function getPhonesAction(ParamFetcherInterface $paramFetcher)
    {
        $pager = $this->repository->search(
            $paramFetcher->get('keyword'),
            $paramFetcher->get('order'),
            $paramFetcher->get('limit'),
            $paramFetcher->get('offset')
        );
        if($pager === false){
            throw new HttpException(204, 'No phone found');
        }
        return new Phones($pager);
    }

    /**
     * @Rest\Post(
     *     path="/admin/phone",
     *     name="phone_post"
     * )
     * @Rest\View(StatusCode=201)
     * @ParamConverter("phone", converter="fos_rest.request_body")
     * @param Phone $phone
     * @param ConstraintViolationList $violations
     * @return View
     */
    public function addPhoneAction(Phone $phone, ConstraintViolationList $violations)
    {
        if (count($violations)){
            $this->errorsFunction($violations);
        }
        $this->em->persist($phone);
        $this->em->flush();

        return $this->view(
            $phone,
            Response::HTTP_CREATED,[
                'Location' => $this->generateUrl('phone_get', ['id' => $phone->getId()], UrlGeneratorInterface::ABSOLUTE_URL)
            ]
        );
    }

    /**
     * @Rest\Patch(
     *     path="/admin/phone/{id}",
     *     name="phone_update",
     *     requirements={"id"="\d+"}
     * )
     * @ParamConverter("phone", converter="fos_rest.request_body")
     * @Rest\View(StatusCode=203)
     * @param Phone $phone
     * @param Request $request
     * @return View
     */

    public function updatePhoneAction(Phone $phone, Request $request)
    {
        $result = $this->repository->findOneBy(['id'=>$request->get('id')]);

        if(empty($result)){
           throw new HttpException(Response::HTTP_NOT_FOUND,"This Phone does not exist");
        }
        if($phone->getName() &&  !empty($phone->getName() && $phone->getName() != $result->getName())){
            $result->setName($phone->getName());
        }

        if($phone->getDescription() && !empty($phone->getDescription()) && $phone->getDescription() != $result->getDescription()){
            $result->setDescription($phone->getDescription());
        }
        if($phone->getPrice() && !empty($phone->getPrice()) && $phone->getPrice() != $result->getPrice()){
            $result->setPrice($phone->getPrice());
        }
        $this->em->persist($result);
        $this->em->flush();
        return $this->view(
            $result,
            Response::HTTP_CREATED,[
                'Location' => $this->generateUrl('phone_get', ['id' => $result->getId()], UrlGeneratorInterface::ABSOLUTE_URL)
            ]
        );
    }

    /**
     * @Rest\Get(
     *     path="/phone/{id}",
     *     name="phone_get",
     *     requirements={"id"="\d+"}
     * )
     * @Rest\View()
     * @param Phone $phone
     * @return Phone
     * @SWG\Response(
     *     response=200,
     *     description="The detail of 1 phone",
     *     @Model(type=Phone::class)
     * )
     * @SWG\Response(
     *     response=404,
     *     description="Page not found"
     * )
     * @SWG\Parameter(
     *     name="id",
     *     in="path",
     *     type="integer",
     *     description="The ID of the phone"
     * )
     * @SWG\Tag(name="Phone")
     * @Security(name="Bearer")
     */
    public function showPhoneAction(Phone $phone)
    {
        return $phone;
    }

    /**
     * @param ConstraintViolationList $violations
     */
    private function errorsFunction(ConstraintViolationList $violations)
    {
        $message = "The JSON sent contains invalid data. Here are the errors you need to correct: ";
        foreach ($violations as $violation) {
            $message .= sprintf(
                "<br> - Field %s: %s",
                $violation->getPropertyPath(),
                $violation->getMessage()
            );
        }
        throw new HttpException(Response::HTTP_BAD_REQUEST, $message);
    }

    /**
     * @Rest\Delete(
     *     path="/admin/phone/{id}",
     *     name="phone_delete"
     * )
     * @param Phone $phone
     * @return string
     */
    public function deletePhoneAction(Phone $phone)
    {
        $this->em->remove($phone);
        $this->em->flush();

        return $this->view('null', Response::HTTP_NO_CONTENT);
    }
}