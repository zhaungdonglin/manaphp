<?php

namespace ManaPHP\Security;

use ManaPHP\Component;
use ManaPHP\Exception\MisuseException;

/**
 * Class ManaPHP\Security\Identity
 */
abstract class Identity extends Component implements IdentityInterface
{
    /**
     * @var string
     */
    protected $_type;

    /**
     * @var array
     */
    protected $_claims;

    /**
     * @return bool
     */
    public function isGuest()
    {
        if ($this->_claims === null) {
            throw new MisuseException('claims is not set');
        }

        return !$this->_claims;
    }

    /**
     * @return int
     */
    public function getId()
    {
        if ($this->_claims === null) {
            throw new MisuseException('claims is not set');
        } elseif ($this->_claims === []) {
            return 0;
        } elseif (!$this->_type) {
            throw new MisuseException('type is unknown');
        } else {
            $id = $this->_type . '_id';
            return isset($this->_claims[$id]) ? $this->_claims[$id] : 0;
        }
    }

    /**
     * @return string
     */
    public function getName()
    {
        if ($this->_claims === null) {
            throw new MisuseException('claims is not set');
        } elseif ($this->_claims === []) {
            return '';
        } elseif (!$this->_type) {
            throw new MisuseException('type is unknown');
        } else {
            $name = $this->_type . '_name';
            return isset($this->_claims[$name]) ? $this->_claims[$name] : '';
        }
    }

    /**
     * @param string     $claim
     * @param string|int $value
     *
     * @return static
     */
    public function setClaim($claim, $value)
    {
        $this->_claims[$claim] = $value;

        return $this;
    }

    /**
     * @param array $claims
     *
     * @return static
     */
    public function setClaims($claims)
    {
        if (!$this->_type) {
            foreach ($claims as $claim => $value) {
                if (strlen($claim) > 3 && strrpos($claim, '_id', -3) !== false) {
                    $this->_type = substr($claim, 0, -3);
                    break;
                }
            }
        }
        $this->_claims = $claims;

        return $this;
    }

    /**
     * @param string     $claim
     * @param string|int $default
     *
     * @return string|int
     */
    public function getClaim($claim, $default = null)
    {
        if ($this->_claims === null) {
            throw new MisuseException('claims is not set');
        }
        return isset($this->_claims[$claim]) ? $this->_claims[$claim] : $default;
    }

    /**
     * @return array
     */
    public function getClaims()
    {
        if ($this->_claims === null) {
            throw new MisuseException('claims is not set');
        }

        return $this->_claims;
    }

    /**
     * @param string $claim
     *
     * @return bool
     */
    public function hasClaims($claim)
    {
        if ($this->_claims === null) {
            throw new MisuseException('claims is not set');
        }
        return isset($this->_claims[$claim]);
    }

    /**
     * @param string $str
     *
     * @return string
     */
    public function base64urlEncode($str)
    {
        return strtr(rtrim(base64_encode($str), '='), '+/', '-_');
    }

    /**
     * @param string $str
     *
     * @return bool|string
     */
    public function base64urlDecode($str)
    {
        return base64_decode(strtr($str, '-_', '+/'));
    }
}