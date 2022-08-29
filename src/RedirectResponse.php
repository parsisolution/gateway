<?php

namespace Parsisolution\Gateway;


class RedirectResponse
{
    const TYPE_GET = 'Get';
    const TYPE_POST = 'Post';

    /**
     * @var string
     */
    private $type;
    /**
     * @var string
     */
    private $url;
    /**
     * @var array
     */
    private $data;

    /**
     * RedirectResponse constructor.
     *
     * @param string $type
     * @param string $url
     * @param array $data
     */
    public function __construct($type, $url, array $data = null)
    {
        $this->type = $type;
        $this->url = $url;
        $this->data = $data;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Redirect the user of the application to the provider's payment screen.
     *
     * @param \Illuminate\Container\Container $app
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Illuminate\Contracts\View\View
     */
    public function redirect($app)
    {
        if ($this->getType() === RedirectResponse::TYPE_GET) {
            return new \Symfony\Component\HttpFoundation\RedirectResponse($this->getUrl());
        } else {
            $data = [
                'URL'  => $this->getUrl(),
                'Data' => $this->getData(),
            ];

            return $this->view($app, 'gateway::redirector')->with($data);
        }
    }

    /**
     * Get the evaluated view contents for the given view.
     *
     * @param \Illuminate\Container\Container $app
     * @param  string $view
     * @param  array $data
     * @param  array $mergeData
     * @return \Illuminate\View\View
     */
    protected function view($app, $view = null, $data = [], $mergeData = [])
    {
        $factory = $app->make(\Illuminate\Contracts\View\Factory::class);

        if (func_num_args() === 0) {
            return $factory;
        }

        return $factory->make($view, $data, $mergeData);
    }
}