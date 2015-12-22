<?php

namespace SnowflakeCo\DataBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use QuReP\ApiBundle\Annotations\Entity\Type as PropType;
use Symfony\Component\Form\Extension\Core\Type as FormType;

/**
 * User
 *
 * @ORM\Table(
 *     name="user"
 * )
 * @ORM\Entity(
 *     repositoryClass="SnowflakeCo\DataBundle\Entity\UserRepository"
 * )
 * @ORM\HasLifecycleCallbacks
 */
class User
{
    public function __construct() {
        $this->roomEntries = new ArrayCollection();
    }


    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="username", type="string", length=255)
     * @PropType(FormType\TextType::class)
     */
    private $username;

    /**
     * @var string
     *
     * @ORM\Column(name="displayName", type="string", length=255)
     * @PropType(FormType\TextType::class)
     */
    private $displayName;

    /**
     * @var string
     *
     * @ORM\Column(name="email", type="string", length=255)
     * @PropType(FormType\EmailType::class)
     */
    private $email;

    /**
     * @var ArrayCollection
     *
     * @ORM\OneToMany(targetEntity="RoomEntry", mappedBy="user")
     */
    private $roomEntries;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="createdAt", type="datetime")
     * @PropType(FormType\DateTimeType::class)
     */
    private $createdAt;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="updatedAt", type="datetime")
     * @PropType(FormType\DateTimeType::class)
     */
    private $updatedAt;


    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set username
     *
     * @param string $username
     *
     * @return User
     */
    public function setUsername($username)
    {
        $this->username = $username;

        return $this;
    }

    /**
     * Get username
     *
     * @return string
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * Set displayName
     *
     * @param string $displayName
     *
     * @return User
     */
    public function setDisplayName($displayName)
    {
        $this->displayName = $displayName;

        return $this;
    }

    /**
     * Get displayName
     *
     * @return string
     */
    public function getDisplayName()
    {
        return $this->displayName;
    }

    /**
     * Set email
     *
     * @param string $email
     *
     * @return User
     */
    public function setEmail($email)
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Get email
     *
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @return \DateTime
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * @param \DateTime $updatedAt
     */
    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;
    }

    /**
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @param \DateTime $createdAt
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;
    }


    /**
     *
     * @ORM\PrePersist
     * @ORM\PreUpdate
     */
    public function updatedTimestamps() {
        $time = new \DateTime('now');
        $this->setUpdatedAt($time);
        if ($this->getCreatedAt() == null) {
            $this->setCreatedAt($time);
        }
    }

    /**
     * @return ArrayCollection
     */
    public function getRoomEntries()
    {
        return $this->roomEntries;
    }

    /**
     * @param ArrayCollection $roomEntries
     */
    public function setRoomEntries($roomEntries)
    {
        $this->roomEntries = $roomEntries;
    }
}

