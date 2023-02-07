<?php

declare(strict_types=1);

namespace Symfony\UX\Typesense\Client;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\UX\Typesense\Exception\TypesenseException;
use Typesense\Aliases;
use Typesense\Client;
use Typesense\Collections;
use Typesense\Debug;
use Typesense\Health;
use Typesense\Keys;
use Typesense\Metrics;
use Typesense\MultiSearch;
use Typesense\Operations;

class TypesenseClient
{
    private $client;

    public function __construct(ParameterBagInterface $parameterBag)
    {
        $this->parameterBag = $parameterBag;
    }

    public function prepare(): array
    {
        $apiKey = $this->parameterBag->get("typesense.server.secret");
        if(!$apiKey) {

            if(is_cli()) throw new TypesenseException("Typesense API Key missing");
            return [];
        }

        $host = $this->parameterBag->get("typesense.server.host");
        $urlParsed = parse_url($host);

        $host     = $urlParsed["host"] ?? $host;
        $port     = $urlParsed["port"] ?? $this->parameterBag->get("typesense.server.host");
        $protocol = $urlParsed["schema"] ?? ($this->parameterBag->get("typesense.server.use_https") ? "https" : "http");

        $nodes = ['host' => $host, 'port' => $port, 'protocol' => $protocol];
        $options = array_merge(
            ['connection_timeout_seconds' => 5],
            $this->parameterBag->get("typesense.server.options") ?? []
        );

        return [$apiKey, $nodes, ...$options];
    }

    public function connect(string $apiKey, array $nodes, ...$options): array
    {
        if(!$this->client) {

            list($nodes, $apiKey, $options) = $this->prepare();
            $this->client = new Client(array_merge($options, ["nodes" => $nodes, "api_key" => $apiKey]));
        }

        return $this->client;
    }

    public function getCollections(): ?Collections
    {
        if(!$this->client)
            $this->client = $this->connect();

        return $this->client?->collections;
    }

    public function getAliases(): ?Aliases
    {
        if(!$this->client)
            $this->client = $this->connect();

        return $this->client?->aliases;
    }

    public function getKeys(): ?Keys
    {
        if(!$this->client)
            $this->client = $this->connect();

        return $this->client?->keys;
    }

    public function getDebug(): ?Debug
    {
        if(!$this->client)
            $this->client = $this->connect();

        return $this->client?->debug;
    }

    public function getMetrics(): ?Metrics
    {
        if(!$this->client)
            $this->client = $this->connect();

        return $this->client?->metrics;
    }

    public function getHealth(): ?Health
    {
        if(!$this->client)
            $this->client = $this->connect();

        return $this->client?->health;
    }

    public function getOperations(): ?Operations
    {
        if(!$this->client)
            $this->client = $this->connect();

        return $this->client?->operations;
    }

    public function getMultiSearch(): ?MultiSearch
    {
        if(!$this->client)
            $this->client = $this->connect();

        return $this->client?->multiSearch;
    }

    /**
     * This allow to be use to use new Typesense\Client functions
     * before we update this client.
     */
    public function __call($name, $arguments)
    {
        if(!$this->client)
            $this->client = $this->connect();

        return $this->client?->{$name}(...$arguments);
    }

    public function __get($name)
    {
        if(!$this->client)
            $this->client = $this->connect();

        return $this->client?->{$name};
    }

    public function isOperationnal(): bool
    {
        return $this->client !== null;
    }
}
