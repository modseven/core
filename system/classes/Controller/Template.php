<?php
/**
 * Abstract controller class for automatic templating.
 *
 * @copyright  (c) 2007-2016  Kohana Team
 * @copyright  (c) 2016-2019  Koseven Team
 * @copyright  (c) since 2019 Modseven Team
 * @license    https://koseven.ga/LICENSE
 */

namespace Modseven\Controller;

use Modseven\Controller;
use Modseven\Exception;
use Modseven\View;

abstract class Template extends Controller
{
    /**
     * Page template
     * @var mixed
     */
    public $template = 'template';

    /**
     * Auto render template
     * @var bool
     **/
    public bool $auto_render = true;

    /**
     * Loads the template View object.
     *
     * @throws Exception
     */
    public function before(): void
    {
        parent::before();

        if ($this->auto_render === true)
        {
            // Load the template
            $this->template = View::factory($this->template);
        }
    }

    /**
     * Assigns the template View as the request response.
     */
    public function after(): void
    {
        if ($this->auto_render === true)
        {
            $this->response->body($this->template->render());
        }

        parent::after();
    }

}
