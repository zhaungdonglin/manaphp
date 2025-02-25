<?php

namespace ManaPHP;

/**
 * Interface ManaPHP\IdentityInterface
 */
interface IdentityInterface
{
    /**
     * @param bool $silent
     *
     * @return static
     */
    public function authenticate($silent = true);

    /**
     * @return bool
     */
    public function isGuest();

    /**
     * @param int $default
     *
     * @return int
     */
    public function getId($default = null);

    /**
     * @param string $default
     *
     * @return string
     */
    public function getName($default = null);

    /**
     * @param string $default
     *
     * @return string
     */
    public function getRole($default = 'guest');

    /**
     * @param string $name
     *
     * @return bool
     */
    public function isRole($name);

    /**
     * @param string $role
     *
     * @return static
     */
    public function setRole($role);

    /**
     * @param string $claim
     * @param mixed  $value
     *
     * @return static
     */
    public function setClaim($claim, $value);

    /**
     * @param array $claims
     *
     * @return static
     */
    public function setClaims($claims);

    /**
     * @param string $claim
     * @param mixed  $default
     *
     * @return mixed
     */
    public function getClaim($claim, $default = null);

    /**
     * @return array
     */
    public function getClaims();

    /**
     * @param string $claim
     *
     * @return bool
     */
    public function hasClaim($claim);
}