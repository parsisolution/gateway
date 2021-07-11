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
}