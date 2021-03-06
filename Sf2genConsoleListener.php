<?php

namespace Sf2gen\Bundle\ConsoleBundle;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Bundle\TwigBundle\TwigEngine;

/**
 * Listener for console
 *
 * @author Cédric Lahouste
 * @author winzou
 *
 * @api
 */
class Sf2genConsoleListener
{
    protected $templating;
    protected $kernel;
    protected $cacheDir;
    protected $cacheFile;

    public function __construct(Kernel $kernel, TwigEngine $templating)
    {
        $this->templating = $templating;
        $this->kernel = $kernel;
        $this->cacheDir = $this->kernel->getCacheDir() . DIRECTORY_SEPARATOR . 'sf2genconsole' . DIRECTORY_SEPARATOR;
        $this->cacheFile = 'commands.json';
    }

    public function onKernelResponse(FilterResponseEvent $event)
    {
        if (HttpKernelInterface::MASTER_REQUEST !== $event->getRequestType()) {
            return;
        }

        $response = $event->getResponse();
        $request = $event->getRequest();

        if (!$this->needConsoleInjection($request, $response)) {
            return;
        }

        $this->injectToolbar($response);
    }

    public function needConsoleInjection(Request $request, Response $response)
    {
        if ($request->isXmlHttpRequest()
            || !$response->headers->has('X-Debug-Token')
            || '3' === substr($response->getStatusCode(), 0, 1)
            || ($response->headers->has('Content-Type') && false === strpos($response->headers->get('Content-Type'), 'html'))
            || 'html' !== $request->getRequestFormat()
        ) {
            return false;
        }

        return true;
    }

    protected function injectToolbar(Response $response)
    {
        if (function_exists('mb_stripos')) {
            $posrFunction = 'mb_strripos';
            $substrFunction = 'mb_substr';
        } else {
            $posrFunction = 'strripos';
            $substrFunction = 'substr';
        }

        $content = $response->getContent();

        if (false !== $pos = $posrFunction($content, '</body>')) {
            $toolbar = "\n".str_replace("\n", '', $this->templating->render(
                'Sf2genConsoleBundle:Console:toolbar_js.html.twig',
                array(
                    'commands' => $this->getCommands(),
                )
            ))."\n";
            $content = $substrFunction($content, 0, $pos).$toolbar.$substrFunction($content, $pos);
            $response->setContent($content);
        }
    }

    protected function getCommands()
    {
        $commands = $this->getCacheContent();

        if ($commands === false) {
            if (!is_dir($this->cacheDir)) {
                mkdir($this->cacheDir, 0777);
            }

            $commands = $this->fetchCommands();

            file_put_contents($this->cacheDir . $this->cacheFile, json_encode($commands));
        } else {
            $commands = json_decode($commands);
        }

        return $commands;
    }

    protected function fetchCommands()
    {
        $application = new Application($this->kernel);
        foreach ($this->kernel->getBundles() as $bundle) {
            $bundle->registerCommands($application);
        }

        return array_keys($application->all());
    }

    protected function getCacheContent()
    {
        if (is_file($this->cacheDir . $this->cacheFile)){
            return file_get_contents($this->cacheDir . $this->cacheFile);
        }

        return false;
    }
}
