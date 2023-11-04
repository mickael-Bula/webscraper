<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Enregistrement en session du thème courant reçu en AJAX
 */
class ThemeController extends AbstractController
{
    /**
     * @Route("/theme", name="app_theme", methods={"POST"})
     * @throws \Exception
     */
    public function updateTheme(Request $request): JsonResponse
    {
        $session = $request->getSession();
        $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        if (isset($data['theme'])) {
            $session->set('theme', $data['theme']);
        }

        return $this->json(['message' => 'Thème mis à jour avec succès.']);
    }
}
