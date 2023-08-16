<?php

declare(strict_types=1);

namespace PinPuzzle;

class PinInstruction
{
    private string $selector = '';
    private string $seed = '';
    private string $water = '';

    public function processor($value)
    {
        $this->selector = $value;
        return $this;
    }

    public function input($value)
    {
        $this->seed = $value;
        return $this;
    }

    public function key($value)
    {
        $this->water = $value;
        return $this;
    }

    public function __toString()
    {
        return  $this->selector . '.' . $this->seed . '.' . $this->water;
    }
}
