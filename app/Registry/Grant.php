<?php

namespace App\Registry;

use InvalidArgumentException;
use JsonSerializable;

class Grant implements JsonSerializable
{
    public function __construct(public ResourceType $type, public string $name, public array $actions = [])
    {
    }

    public function restrictTo(array $actions): static {
        $actions = array_filter($actions, fn ($action) => !in_array($action, $this->actions));
        return new static($this->type, $this->name, array_merge($this->actions, $actions));
    }

    public function jsonSerialize(): mixed
    {
        return [
            'type' => $this->type->value,
            'name' => $this->name,
            'actions' => array_map(fn(Action $action) => $action->value, $this->actions),
        ];
    }

    public static function parse(string $scope): static
    {
        $parts = explode(':', $scope, 3);
        if (count($parts) < 2 || count($parts) > 3) {
            throw new InvalidArgumentException("Invalid scope format: $scope");
        }

        $type = ResourceType::from($parts[0]);
        $name = $parts[1];
        $actions = isset($parts[2]) ? explode(',', $parts[2]) : [];

        return new static($type, $name, array_map(fn($str) => Action::from(trim($str)), $actions));
    }
}
