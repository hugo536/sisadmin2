<?php
declare(strict_types=1);

class UploadHelper
{
    /**
     * @param string $inputName
     * @param string $relativeDir
     * @param int $maxSize
     * @param array $mimePermitidos
     * @param string|null $archivoAnterior
     * @return string|null
     */
    public static function subirImagen(
        string $inputName,
        string $relativeDir,
        int $maxSize,
        array $mimePermitidos,
        ?string $archivoAnterior = null
    ): ?string {
        if (!isset($_FILES[$inputName]) || $_FILES[$inputName]['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        $file = $_FILES[$inputName];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Error en la subida del archivo.');
        }
        if ($file['size'] > $maxSize) {
            throw new RuntimeException('El archivo excede el tamaño permitido.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        if (!in_array($finfo->file($file['tmp_name']), $mimePermitidos, true)) {
            throw new RuntimeException('Formato de archivo no válido.');
        }

        $absoluteDir = BASE_PATH . '/public/' . trim($relativeDir, '/');

        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0755, true)) {
            throw new RuntimeException('No se pudo crear el directorio de subida.');
        }

        $extension = strtolower((string) pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = uniqid('file_', true) . ($extension !== '' ? '.' . $extension : '');

        if (!move_uploaded_file($file['tmp_name'], $absoluteDir . '/' . $filename)) {
            throw new RuntimeException('Error al guardar el archivo en el servidor.');
        }

        if (!empty($archivoAnterior)) {
            $pathAnterior = BASE_PATH . '/public/' . ltrim($archivoAnterior, '/');
            if (file_exists($pathAnterior) && is_file($pathAnterior)) {
                @unlink($pathAnterior);
            }
        }

        return trim($relativeDir, '/') . '/' . $filename;
    }
}
