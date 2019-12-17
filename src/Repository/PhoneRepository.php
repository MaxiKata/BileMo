<?php

namespace App\Repository;

use App\Entity\Phone;
use FOS\RestBundle\Controller\Annotations as Rest;
use Doctrine\Common\Persistence\ManagerRegistry;
use Pagerfanta\Pagerfanta;


/**
 * @method Phone|null find($id, $lockMode = null, $lockVersion = null)
 * @method Phone|null findOneBy(array $criteria, array $orderBy = null)
 * @method Phone[]    findAll()
 * @method Phone[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PhoneRepository extends AbstractRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Phone::class);
    }

    /**
     * @param $term
     * @param string $order
     * @param int $limit
     * @param int $offset
     * @return Pagerfanta | bool
     * @Rest\View()
     */
    public function search($term, $order = 'asc', $limit = 20, $offset = 0)
    {
        $qb = $this
            ->createQueryBuilder('a')
            ->select('a')
            ->orderBy('a.name', $order)
        ;
        if($term){
            $qb
                ->where('a.name LIKE? 1')
                ->setParameter(1, '%' . $term . '%')
            ;
        }

        $paginate = $this->paginate($qb, $limit, $offset);
        /* check if result have been found */
        if(empty($paginate->getNbResults())){
            return false;

        }

        return $this->paginate($qb, $limit, $offset);
    }
    // /**
    //  * @return Phone[] Returns an array of Phone objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('p.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Phone
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
