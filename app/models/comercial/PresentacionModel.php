<?php
class PresentacionModel extends Modelo {
    public function __call(string $name, array $arguments) {
        throw new RuntimeException('El módulo de presentaciones y packs fue retirado del sistema.');
    }
}
