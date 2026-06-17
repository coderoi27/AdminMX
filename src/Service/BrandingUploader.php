<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

class BrandingUploader
{
    private string $targetDirectory;
    private SluggerInterface $slugger;

    public function __construct(
        #[Autowire('%kernel.project_dir%')] string $projectDir, 
        SluggerInterface $slugger
    ) {
        // Guardar físicamente en /public/uploads/branding
        $this->targetDirectory = $projectDir . '/public/uploads/branding';
        $this->slugger = $slugger;
    }

    public function upload(UploadedFile $file, string $prefix = ''): string
    {
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $fileName = $prefix . $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

        // Asegurar que el directorio exista
        if (!is_dir($this->targetDirectory)) {
            mkdir($this->targetDirectory, 0777, true);
        }

        try {
            $file->move($this->getTargetDirectory(), $fileName);
        } catch (FileException $e) {
            throw new \RuntimeException('Error al subir el archivo de branding: ' . $e->getMessage());
        }

        // Devolver la ruta pública relativa
        return '/uploads/branding/' . $fileName;
    }

    public function getTargetDirectory(): string
    {
        return $this->targetDirectory;
    }
}
