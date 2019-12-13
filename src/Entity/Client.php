<?php

/*
 * This file is part of the FOSOAuthServerBundle package.
 *
 * (c) FriendsOfSymfony <http://friendsofsymfony.github.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Entity;

use FOS\OAuthServerBundle\Model\Client as BaseClient;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\ClientRepository")
 */
class Client extends BaseClient
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\Column(type="string")
     */
    protected $randomId;

    /**
     * @ORM\Column(type="string")
     */
    protected $secret;

    /**
     * @ORM\Column(type="simple_array")
     * @var array
     */
    protected $redirectUris = array();

    /**
     * @ORM\Column(type="simple_array")
     * @var array
     */
    protected $allowedGrantTypes = array();

    /**
     * @ORM\Column(type="string")
     * @var string
     */
    protected $name;

    public function __construct()
    {
        parent::__construct();
    }
}
