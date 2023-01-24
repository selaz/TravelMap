<?php

namespace Selaz\Entities;

class Host {

    private $attributes = [];

    public function __construct(
        protected string $host,
        protected int $port,
        protected ?string $protocol = null,
        protected ?string $login = null,
        protected ?string $password = null,
        protected ?string $path = null
    )
    {}

    public function setAttribute(string $attribute, string $value) {
        $this->attributes[$attribute] = $value;
    }

    public function getAttribute(string $name) {
        return $this->attributes[$name] ?? null;
    }

    public function getAttributes() {
        return $this->attributes;
    }

    public function setHost(string $host) {
        $this->host = $host;
    }

    public function setPort(int $port) {
        $this->port = $port;
    }

    public function getHost() {
        return $this->host;
    }

    public function getPort() {
        return $this->port;
    }

    public function getProtocol() {
        return $this->protocol;
    }

    public function getLogin() {
        return $this->login;
    }

    public function getPassword() {
        return $this->password;
    }

    public function getPath() {
        return $this->path;
    }

    public function getUri() {
        if (empty($this->getProtocol())) {
            return sprintf('%s:%d%s',$this->getHost(),$this->getPort(),$this->getPath());
        }
        return sprintf('%s://%s:%d%s',$this->getProtocol(),$this->getHost(),$this->getPort(),$this->getPath());
    }

    public function __toString() {
        return $this->getUri();
    }
}