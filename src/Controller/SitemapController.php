<?php

namespace InSquare\OpendxpSitemapBundle\Controller;

use OpenDxp\Model\Site;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SitemapController extends AbstractController
{
    private string $outputDir;

    public function __construct(#[Autowire('%in_square_opendxp_sitemap%')] array $config)
    {
        $output = $config['output'] ?? [];
        $this->outputDir = rtrim((string) ($output['dir'] ?? ''), '/');
    }

    #[Route('/sitemap.xml', name: 'insquare_sitemap_xml', methods: ['GET'])]
    public function sitemap(Request $request): Response
    {
        if ($this->outputDir === '') {
            return new Response('Sitemap output directory not configured.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $siteId = 0;
        if (Site::isSiteRequest()) {
            $site = Site::getCurrentSite();
            if ($site instanceof Site) {
                $siteId = (int) $site->getId();
            }
        }

        $filePath = sprintf('%s/sitemap.%d.xml', $this->outputDir, $siteId);
        if (!is_file($filePath)) {
            return new Response('Sitemap file not found.', Response::HTTP_NOT_FOUND);
        }

        $response = new BinaryFileResponse($filePath);
        $response->setPublic();
        $response->headers->set('Content-Type', 'application/xml');
        $response->setAutoEtag();
        $response->setAutoLastModified();

        if ($response->isNotModified($request)) {
            return $response;
        }

        return $response;
    }
}
