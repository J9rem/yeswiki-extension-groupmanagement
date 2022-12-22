<?php

/*
 * This file is part of the YesWiki Extension groupmanagement.
 *
 * Authors : see README.md file that was distributed with this source code.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace YesWiki\Groupmanagement\Controller;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use YesWiki\Comschange\Service\CommentService;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\TemplateEngine;
use YesWiki\Core\YesWikiEventCompilerPass;
use YesWiki\Core\YesWikiController;
use YesWiki\Groupmanagement\Entity\DataContainer;
use YesWiki\Groupmanagement\Service\EventDispatcherInterface;
use YesWiki\Wiki;

class GroupController extends YesWikiController implements EventSubscriberInterface
{
    protected $aclService;
    protected $eventDispatcher;
    protected $params;
    protected $templateEngine;

    public function __construct(
        AclService $aclService,
        EventDispatcherInterface $eventDispatcher,
        ParameterBagInterface $params,
        TemplateEngine $templateEngine,
        Wiki $wiki
    ) {
        $this->aclService = $aclService;
        $this->eventDispatcher = $eventDispatcher;
        $this->params = $params;
        $this->templateEngine = $templateEngine;
        $this->wiki = $wiki;
    }

    public static function getSubscribedEvents()
    {
        return [
            'groupmanagement.bazarliste.entriesready' => 'displayOnlyWritable',
            'groupmanagement.bazarliste.afterdynamicquery' => 'keepOnlyWritableIntoResponse',
        ];
    }
    public function displayOnlyWritable($event)
    {
        $eventData = $event->getData();
        if (!empty($eventData) && is_array($eventData) && isset($eventData['dataContainer']) && ($eventData['dataContainer'] instanceof DataContainer)) {
            $bazarData = $eventData['dataContainer']->getData();
            $arg = $bazarData['param'] ?? [];
            $keepOnlyEntriesWhereCanEdit = $arg['keeponlyentrieswherecanedit'] ?? false;
            if ($keepOnlyEntriesWhereCanEdit) {
                foreach ($bazarData['fiches'] as $idx => $entry) {
                    if (empty($entry['id_fiche']) || !$this->aclService->hasAccess('write', $entry['id_fiche'])) {
                        unset($bazarData['fiches'][$idx]);
                    }
                }
                $eventData['dataContainer']->setData($bazarData);
            }
        }
    }


    public function keepOnlyWritableIntoResponse($event)
    {
        if (!$this->wiki->UserIsAdmin()) {
            $keepOnlyEntriesWhereCanEdit = in_array($_GET['keeponlyentrieswherecanedit'] ?? false, [1,true,"1","true"], true);
            if ($keepOnlyEntriesWhereCanEdit) {
                $eventData = $event->getData();
                if (!empty($eventData) &&
                    is_array($eventData) &&
                    isset($eventData['response']) &&
                    method_exists($eventData['response'], 'getContent')) {
                    $response = $eventData['response'];
                    $status = $response->getStatusCode();
                    if ($status < 400) {
                        $content = $response->getContent();
                        $contentDecoded = json_decode($content, true);
                        if (!empty($contentDecoded) && !empty($contentDecoded['entries'])) {
                            $fieldMapping = $contentDecoded['fieldMapping'] ?? [];
                            $idFicheIdx = array_search("id_fiche", $fieldMapping);
                            if ($idFicheIdx !== false && $idFicheIdx > -1) {
                                $contentDecoded['entries'] = array_values(array_filter($contentDecoded['entries'], function ($entry) use ($idFicheIdx) {
                                    return !empty($entry[$idFicheIdx]) && $this->aclService->hasAccess('write', $entry[$idFicheIdx]);
                                }));
                                $response->setData($contentDecoded);
                            }
                        }
                    }
                }
            }
        }
    }

    public function defineBazarListeActionParams(array $arg, array $get, $callable): array
    {
        $isDynamic = (
            isset($arg['dynamic']) && (
                (is_bool($arg['dynamic']) && $arg['dynamic']) ||
                (!is_bool($arg['dynamic']) && !empty($arg['dynamic']) && !in_array($arg['dynamic'], [0,'0','no','non','false'], true))
            )
        );
        $template = $get['template'] ?? $arg['template'] ?? null;
        $template = is_string($template) ? $template : null;
        if (($template === 'calendar.tpl.html' && !$this->templateEngine->hasTemplate("@bazar/{$template}")) ||
            ($template === 'calendar' && !$this->templateEngine->hasTemplate("@bazar/{$template}.tpl.html"))) {
            $template = "calendar";
            $isDynamic = true;
        }
        $isDynamic = $isDynamic || in_array($template, ['card','list']);
        list('replaceTemplate'=>$replaceTemplate, 'options'=>$options) = $callable($isDynamic, $this->wiki->UserIsAdmin(), $arg);
        if (!is_array($options)) {
            $options = [];
        }
        if ($replaceTemplate) {
            $newTemplate = in_array($template, ["map","map.tpl.html","gogocarto","gogocarto.tpl.html"]) ? $template : "groupmanagement_pre_template.tpl.html";
            return [
                'template' => $newTemplate,
                'previous-template' => ($template != "groupmanagement_pre_template.tpl.html") ? $template : ($arg['previous-template'] ?? null),
            ]+$options;
        } else {
            return $options;
        }
    }

    public function renderTemplate(array $data, string $file = "", array $bazarPaths = []): string
    {
        if (!isset($data['param'])) {
            $data['param'] = [];
        }
        $param = $data['param'];
        $template = $param['template'] ?? "";
        $isDirect = false;
        if ($template == "groupmanagement_pre_template.tpl.html") {
            $template = $param['previous-template'] ?? "";
        } elseif (!empty($file) && !empty($bazarPaths)) {
            $formattedFolderName = str_replace("\\", "/", dirname($file));
            if (preg_match("/.*tools\/([a-z0-9]+)\/templates\/bazar$/", $formattedFolderName, $matches)) {
                $currentRelativeDir = "tools/{$matches[1]}/templates/bazar";
                $isDirect = true;
            } elseif (preg_match("/.*themes\/([A-Za-z0-9_\-]+)\/tools\/bazar(?:\/(templates))?\/$/", $formattedFolderName, $matches)) {
                $currentRelativeDir = (empty($matches[2]))
                    ? "themes/{$matches[1]}/tools/bazar"
                    : "themes/{$matches[1]}/tools/bazar/templates" ;
                $isDirect = true;
            } else {
                $paths = [
                    "custom/themes/tools/bazar/templates",
                    "custom/templates/bazar/templates",
                    "custom/templates/bazar",
                    "templates/bazar/templates",
                    "templates/bazar",
                    "themes/tools/bazar/templates",
                    "themes/tools/bazar",
                    "tools/bazar/presentation/templates",
                    "tools/bazar/templates",
                ];
                foreach ($paths as $path) {
                    if (substr($formattedFolderName, -strlen($path)) == $path) {
                        $currentRelativeDir = $path;
                        $isDirect = true;
                        break;
                    }
                }
            }
            if ($isDirect) {
                $curPos = array_search($currentRelativeDir, $paths);
                if ($curPos !== false) {
                    $fileName = basename($file);
                    for ($i=($curPos+1); $i < count($paths); $i++) {
                        if ($paths[$i] != $currentRelativeDir && $this->templateEngine->hasTemplate("{$paths[$i]}/$fileName")) {
                            $template = "{$paths[$i]}/$fileName";
                            $isDirect = true;
                        }
                    }
                }
            }
        }
        $template = (!empty($template)) ? $template : $this->params->get('default_bazar_template');
        if (strpos($template, '.html') === false && strpos($template, '.twig') === false) {
            $template = $template . '.tpl.html';
        }
        $templatePath = $isDirect ? $template : "@bazar/{$template}";
        $data['param']['template'] = basename($template);
        $dataContainer = new DataContainer($data);
        $this->eventDispatcher->yesWikiDispatch('groupmanagement.bazarliste.entriesready', compact(['dataContainer']));
        $data = $dataContainer->getData();
        try {
            return $this->render($templatePath, $data);
        } catch (TemplateNotFound $e) {
            return '<div class="alert alert-danger">'.$e->getMessage().'</div>';
        }
    }

    public function registerSubscribers()
    {
        if (!class_exists(YesWikiEventCompilerPass::class, false)) {
            $containerBuilder = $this->wiki->services;
            if ($containerBuilder && $containerBuilder instanceof ContainerBuilder) {
                if ($containerBuilder->has(CommentService::class)) {
                    $commentService = $containerBuilder->get(CommentService::class);
                    if (method_exists($this->eventDispatcher, 'hasListeners') && !$this->eventDispatcher->hasListeners()) {
                        $commentService->registerSubscribers();
                    }
                }
            }
        }
    }
}
