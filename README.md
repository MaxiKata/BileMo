# BileMo

It is the Seventh project of my studies. - API
Project made with Symfony 4.4.

[![Codacy Badge](https://api.codacy.com/project/badge/Grade/eb60ee833a8e40afb6f5ddfa68720231)](https://www.codacy.com/manual/MaxiKata/BileMo?utm_source=github.com&amp;utm_medium=referral&amp;utm_content=MaxiKata/BileMo&amp;utm_campaign=Badge_Grade)
![OenClassRooms](https://img.shields.io/badge/OpenClassRooms-DA_PHP/SF-blue.svg)
![Project](https://img.shields.io/badge/Project-7-blue.svg)
![Type](https://img.shields.io/badge/Type-API_FOSRest-blue.svg) 
![PHP](https://img.shields.io/badge/Symfony-4.4-blue.svg)
![Security](https://img.shields.io/badge/Security-Oauth2-blue.svg) 

## Installation
### 1 - Install Composer
**=>** [https://getcomposer.org/download/](https://getcomposer.org/download/)

### 2 - Install Symfony
**=>** [https://symfony.com/download](https://symfony.com/download)

**=>** [https://symfony.com/doc/current/setup.html](https://symfony.com/doc/current/setup.html)

### 3 - Modify Connection in .env

**=>** Edit line 27 in file .env in root with your database details

## Last Settings

### 1 - Add Property name to Client Entity 

**=>** A property "name" has been added on the basic settings for the Client Entity. You might need to add it to few files in composer such as:

-   FOS\OAuthServerBundle\Model\Client with: 

```alpha
/**
* @ORM\Column(type="string")
* @Serializer\Expose()
* @var string
*/
protected $name;
```

-   FOS\OAuthServerBundle\Model\ClientInterface with:

```alpha
/**
* @param string $name
*/
public function setName(string $name);
    
/**
* @return string
*/
public function getName();
```

### 2 - Manage property access

**=>** You might wish to hide some params from being shown to user or client when they call a user or users list. In this case, you can add:

-   Comment the entity with (this will hide and protect all properties):
```alpha
/**
* @Serializer\ExclusionPolicy("all")
*/
```

-   Comment the property you wish to show with:
```alpha
/**
* @Serializer\Expose()
*/
```

## Usage - Url
    
You can easily manage the authorization in config/packages/security.yaml and set *IS_AUTHENTICATED_ANONYMOUSLY* as roles in "access_control" to ensure your first adding client.

**We strongly recommend to put back the basic settings once 1st Client will be created**
    
-   To add a new Client call: *api/admin/createClient*
-   To connect as user: *api/login*

Once logged in, the API will send back a token that you will be required for every connection and should be sent in headers as:
-   Key = ```X-AUTH-TOKEN```
-   Value = *User token*

The address that user can check are:

| URL            | Entity | Call Method | Description                 |
| -------------- | ------ | ----------- | --------------------------- |
| api/user/{id}  | User   | GET         | Get a user profile          |
| api/user/{id}  | User   | DELETE      | Delete a user profile       |
| api/users/{id} | User   | GET         | Get users lists of a client |
| api/phone/{id} | Phone  | GET         | Get a phone description     |
| api/phones     | Phone  | GET         | Get the list of phones      |

The address that admin can check are:

| URL                    | Entity | Call Method | Description         |
| ---------------------- | ------ | ----------- | ------------------- |
| api/admin/createClient | Client | POST        | Create a new Client |
| api/admin/deleteClient | Client | DELETE      | Delete a Client     |
| api/admin/phone        | Phone  | POST        | Add a new Phone     |
| api/admin/phone/{id}   | Phone  | PATCH       | Update a Phone      |
| api/admin/phone/{id}   | Phone  | DELETE      | Delete a Phone      |

## License

[MIT](https://github.com/MaxiKata/BileMo/blob/master/LICENSE.md)

![Permissions](https://img.shields.io/badge/Permissions-Commercial_use-green.svg) 
![Permissions](https://img.shields.io/badge/Permissions-Distribution-green.svg) 
![Permissions](https://img.shields.io/badge/Permissions-Modification-green.svg) 
![Permissions](https://img.shields.io/badge/Permissions-Private_use-green.svg)

![Conditions](https://img.shields.io/badge/Conditions-License_and_copyright_notice-blue.svg)

![Limitations](https://img.shields.io/badge/Conditions-Liability-red.svg)
![Limitations](https://img.shields.io/badge/Conditions-Warranty-red.svg)