<?php

namespace Webkul\ImageGalleryBundle\Twig;

use Webkul\ImageGalleryBundle\Listener\ScriptNonceGenerator;
use Webkul\ImageGalleryBundle\Listener\ClassDefinationForCompatibility;

$versionCompatiblility = new ClassDefinationForCompatibility();
$versionCompatiblility->createClassAliases();


/**
 * CSP twig extension.
 *
 * This extension can inject a nonce in javascript tags to make them pass the CSP policy..
 */
class ContentSecurityPolicy extends \Twig_Extension
{
    /** @var ScriptNonceGenerator */
    private $scriptNonceGenerator;

    public function __construct(ScriptNonceGenerator $scriptNonceGenerator)
    {
        $this->scriptNonceGenerator = $scriptNonceGenerator;
    }

    /**
     * {@inheritdoc}
     */
    public function getFunctions()
    {
        return [
            new \Twig_SimpleFunction('js_wk_nonce', [$this, 'getScriptNonce']),
        ];
    }

    public function getScriptNonce()
    {
        return $this->scriptNonceGenerator->getGeneratedNonce();
    }
}
