<?php
/**
 * Native PHP session class.
 *
 * @package    Modseven
 * @category   Session
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2019  Koseven Team
 * @copyright  (c) since 2019 Modseven Team
 * @license    https://koseven.ga/LICENSE
 */

namespace Modseven\Session;

use Modseven\Cookie;
use Modseven\Session;

class Native extends Session
{
    /**
     * @return  string
     */
    public function id(): string
    {
        return session_id();
    }

    /**
     * @param string $id session id
     * @return null|string
     */
    protected function _read(?string $id = NULL): ?string
    {
        /**
         * session_set_cookie_params will override php ini settings
         * If Cookie::$domain is NULL or empty and is passed, PHP
         * will override ini and sent cookies with the host name
         * of the server which generated the cookie
         *
         * see issue #3604
         *
         * see http://www.php.net/manual/en/function.session-set-cookie-params.php
         * see http://www.php.net/manual/en/session.configuration.php#ini.session.cookie-domain
         *
         * set to Cookie::$domain if available, otherwise default to ini setting
         */
        $session_cookie_domain = empty(Cookie::$domain)
            ? ini_get('session.cookie_domain')
            : Cookie::$domain;

        // Sync up the session cookie with Cookie parameters
        session_set_cookie_params(
            $this->_lifetime,
            Cookie::$path,
            $session_cookie_domain,
            Cookie::$secure,
            Cookie::$httponly
        );

        // Do not allow PHP to send Cache-Control headers
        $limiter = session_cache_limiter(FALSE);

        // Set the session cookie name
        session_name($this->_name);

        if ($id) {
            // Set the session id
            session_id($id);
        }

        // Start the session
        try {
            session_start();
        } catch (\Exception $e) {
            $this->_destroy();
            session_start();
        }

        // Use the $_SESSION global for storing data
        $this->_data =& $_SESSION;

        return NULL;
    }

    /**
     * @return  bool
     */
    protected function _destroy(): bool
    {
        // Destroy the current session
        session_destroy();

        // Did destruction work?
        $status = !session_id();

        if ($status) {
            // Make sure the session cannot be restarted
            Cookie::delete($this->_name);
        }

        return $status;
    }

    /**
     * @return  string
     */
    protected function _regenerate(): string
    {
        // Regenerate the session id
        session_regenerate_id();

        return session_id();
    }

    /**
     * @return  bool
     */
    protected function _write(): bool
    {
        // Write and close the session
        session_write_close();

        return TRUE;
    }

    /**
     * @return  bool
     */
    protected function _restart(): bool
    {
        // Fire up a new session
        $status = session_start();

        // Use the $_SESSION global for storing data
        $this->_data =& $_SESSION;

        return $status;
    }

}
