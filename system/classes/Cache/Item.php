<?php

namespace Modseven\Cache;

use Psr\Cache\CacheItemInterface;

class Item implements CacheItemInterface
{

    protected ?string $_sanitizedKey = null;
    protected string $_key;

    public function setKey(string $key) : void
    {
        $this->_key = $key;
    }

    /**
     * Returns the key for the current cache item.
     *
     * @return string The key string for this cache item.
     */
    public function getKey() : string
    {
        return $this->_key;
    }

    public function getSanitizedKey() : ?string
    {
        return $this->_sanitizedKey;
    }

    public function setSanitizedKey(string $key) : void
    {
        $this->_sanitizedKey = $key;
    }

    /**
     * Retrieves the value of the item from the cache associated with this object's key.
     *
     * The value returned must be identical to the value originally stored by set().
     *
     * If isHit() returns false, this method MUST return null. Note that null
     * is a legitimate cached value, so the isHit() method SHOULD be used to
     * differentiate between "null value was found" and "no value was found."
     *
     * @return mixed The value corresponding to this cache item's key, or null if not found.
     */
    public function get() {

    }

    /**
     * Confirms if the cache item lookup resulted in a cache hit.
     *
     * Note: This method MUST NOT have a race condition between calling isHit()
     * and calling get().
     *
     * @return bool True if the request resulted in a cache hit. False otherwise.
     */
    public function isHit() : bool
    {
        // TODO: Implement isHit() method.
    }

    public function getLifeTime() : int {
        return 3600;
    }

    /**
     * Sets the value represented by this cache item.
     *
     * The $value argument may be any item that can be serialized by PHP,
     * although the method of serialization is left up to the Implementing
     * Library.
     *
     * @param mixed $value The serializable value to be stored.
     *
     * @return self
     */
    public function set($value) : self
    {
        // TODO: Implement set() method.
    }

    public function getTags() : array {
        return [];
    }

    /**
     * Sets the expiration time for this cache item.
     *
     * @param \DateTimeInterface|null $expiration
     *   The point in time after which the item MUST be considered expired.
     *   If null is passed explicitly, a default value MAY be used. If none is set,
     *   the value should be stored permanently or for as long as the
     *   implementation allows.
     *
     * @return self
     */
    public function expiresAt($expiration) : self
    {
        // TODO: Implement expiresAt() method.
    }

    /**
     * Sets the expiration time for this cache item.
     *
     * @param int|\DateInterval|null $time
     *   The period of time from the present after which the item MUST be considered
     *   expired. An integer parameter is understood to be the time in seconds until
     *   expiration. If null is passed explicitly, a default value MAY be used.
     *   If none is set, the value should be stored permanently or for as long as the
     *   implementation allows.
     *
     * @return self
     */
    public function expiresAfter($time) : self
    {
        // TODO: Implement expiresAfter() method.
    }
}