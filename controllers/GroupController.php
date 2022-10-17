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
use YesWiki\Core\Controller\AuthController;
use YesWiki\Core\Service\AclService;
use YesWiki\Core\Service\DbService;
use YesWiki\Core\Service\TemplateEngine;
use YesWiki\Core\Service\UserManager;
use YesWiki\Core\Service\YesWikiEventCompilerPass;
use YesWiki\Core\YesWikiController;
use YesWiki\Groupmanagement\Entity\DataContainer;
use YesWiki\Groupmanagement\Service\EventDispatcherInterface;
use YesWiki\Groupmanagement\Service\GroupManagementService;
use YesWiki\Wiki;

class GroupController extends YesWikiController implements EventSubscriberInterface
{
    protected $aclService;
    protected $authController;
    protected $dbService;
    protected $eventDispatcher;
    protected $groupManagementService;
    protected $params;
    protected $userManager;
    protected $templateEngine;

    public function __construct(
        AclService $aclService,
        AuthController $authController,
        DbService $dbService,
        EventDispatcherInterface $eventDispatcher,
        GroupManagementService $groupManagementService,
        ParameterBagInterface $params,
        UserManager $userManager,
        TemplateEngine $templateEngine,
        Wiki $wiki
    ) {
        $this->aclService = $aclService;
        $this->authController = $authController;
        $this->dbService = $dbService;
        $this->eventDispatcher = $eventDispatcher;
        $this->groupManagementService = $groupManagementService;
        $this->params = $params;
        $this->templateEngine = $templateEngine;
        $this->userManager = $userManager;
        $this->wiki = $wiki;
    }

    public static function getSubscribedEvents()
    {
        return [
            'groupmanagement.bazarliste.entriesready' => 'displayOnlyWritable',
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

    public function getWritableEntriesIds(array $formsIds): array
    {
        if (empty($formsIds)) {
            return [];
        }
        $forms = join(' OR ', array_map(function ($formId) {
            return "`body` LIKE '%\"id_typeannonce\":\"{$this->dbService->escape(strval($formId))}\"%'";
        }, $formsIds));
        $query=
        <<<SQL
        SELECT DISTINCT `tag` FROM {$this->dbService->prefixTable('pages')} 
            WHERE `latest`="Y" AND `comment_on` = '' AND ($forms) AND `tag` IN (
                SELECT DISTINCT resource FROM {$this->dbService->prefixTable('triples')}
                WHERE `value` = "fiche_bazar" AND `property` = "http://outils-reseaux.org/_vocabulary/type" 
                ORDER BY resource ASC 
            ) {$this->updateRequestWithWriteACL()} ;
        SQL;
        $results = $this->dbService->loadAll($query);
        return (empty($results)) ? [] : array_map(function ($page) {
            return $page['tag'];
        }, $results);
    }

    public function updateRequestWithWriteACL(): string
    {
        // needed ACL
        $neededACL = ['*'];
        // connected ?
        $user = $this->authController->getLoggedUser();
        if (!empty($user)) {
            $userName = $user['name'];
            $neededACL[] = '+';
            $neededACL[] = $userName;
            $groups = $this->wiki->GetGroupsList();
            foreach ($groups as $group) {
                if ($this->userManager->isInGroup($group, $userName, true)) {
                    $neededACL[] = '@'.$group;
                }
            }
        }

        // check default writeacl
        $newRequestStart = ' AND ';
        $newRequestEnd = '';
        if ($this->aclService->check($this->params->has('default_write_acl') ? $this->params->get('default_write_acl') : '*')) {
            // current user can display pages without write acl
            $newRequestStart .= '(';
            $newRequestEnd = ')'.$newRequestEnd;

            $newRequestStart .= 'tag NOT IN (SELECT DISTINCT page_tag FROM ' . $this->dbService->prefixTable('acls') .
            'WHERE privilege="write")';

            $newRequestStart .= ' OR (';
            $newRequestEnd = ')'.$newRequestEnd;
        }
        // construct new request when acl
        $newRequestStart .= 'tag in (SELECT DISTINCT page_tag FROM ' . $this->dbService->prefixTable('acls') .
            'WHERE privilege="write"';
        $newRequestEnd = ')'.$newRequestEnd;

        // needed ACL
        if (count($neededACL) > 0) {
            $newRequestStart .= ' AND (';
            if (!empty($user)) {
                $newRequestStart .= '(';
                $newRequestEnd = ')'.$newRequestEnd;
            }

            $addOr = false;
            foreach ($neededACL as $acl) {
                if ($addOr) {
                    $newRequestStart .= ' OR ';
                } else {
                    $addOr = true;
                }
                $newRequestStart .= ' list LIKE "%'.$acl.'%"';
            }
            $newRequestStart .= ')';
            // not authorized ACL
            foreach ($neededACL as $acl) {
                $newRequestStart .= ' AND ';
                $newRequestStart .= ' list NOT LIKE "%!'.$acl.'%"';
            }

            // add detection of '%'
            if (!empty($user)) {
                $newRequestStart .= ') OR (';

                $newRequestStart .= '(list LIKE "%\\%%" AND list NOT LIKE "%!\\%%")';
                $newRequestStart .= ' AND owner = _utf8\'' . $this->dbService->escape($userName) . '\'';
            }
        }

        $request = $newRequestStart.$newRequestEnd;

        // return request to append
        return $request;
    }
}
