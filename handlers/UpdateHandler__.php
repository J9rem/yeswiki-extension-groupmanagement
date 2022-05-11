<?php

namespace YesWiki\Groupmanagement;

use YesWiki\Core\YesWikiHandler;
use YesWiki\Security\Controller\SecurityController;

class UpdateHandler__ extends YesWikiHandler
{
    private const BAZARLISTEYAML_PATH = 'docs/actions/bazarliste.yaml';

    public function run()
    {
        if ($this->getService(SecurityController::class)->isWikiHibernated()) {
            throw new \Exception(_t('WIKI_IN_HIBERNATION'));
        };
        if (!$this->wiki->UserIsAdmin()) {
            return null;
        }
        $output = '<strong>UPdate for extension groupmanagement</strong><br />';
        
        $messages = [];
        $state = 'success';
        extract($this->updateBazarListeYaml($messages, $state));

        if (!empty($messages)) {
            $output .= '<div class="alert alert-'.$state.'">';
            if ($state !== 'success') {
                $output .= 'The update could not have worked because:';
            }
            $output .= '<ul>';
            foreach ($messages as $message) {
                $output .= '<li>'.$message.'</li>';
            }
            $output .= '</ul>';
            $output .= '</div>';
        }

        // set output
        $this->output = str_replace(
            '<!-- end handler /update -->',
            $output.'<!-- end handler /update -->',
            $this->output
        );
        return null;
    }
    
    private function updateBazarListeYaml(array $messages, string $state): array
    {
        if (!file_exists(self::BAZARLISTEYAML_PATH)) {
            $messages[] = 'The file bazarliste.yaml was not found!';
            $state = 'danger';
        } else {
            $content = file_get_contents(self::BAZARLISTEYAML_PATH);
            $replacement =
                "keeponlyentrieswherecanedit:\n".
                "        label: _t(GRPMNGT_BAZARLISTE_PARAM_LABEL)\n".
                "        type: checkbox\n".
                "        default: \"false\"\n".
                "        advanced: true";
            $patternBefore = "/bazarcalendar\s*showexportbuttons:\s*/";
            $patternReplace = "/bazarcalendar(\s*)showexportbuttons:(\s*)/";
            $patternReplacement = "bazarcalendar$1$replacement$1showexportbuttons:$2";
            $patternAfter = "/bazarcalendar\s*".preg_quote($replacement)."\s*showexportbuttons:\s*/";
            if (preg_match($patternAfter, $content, $matches)) {
                $messages[] = 'File bazarliste.yaml already up to date!';
            } elseif (!preg_match($patternBefore, $content, $matches)) {
                $messages[] = 'Not possible to update file bazarliste.yaml (current revision was not waited) !';
                $state = 'danger';
            } else {
                $newContent = preg_replace($patternReplace, $patternReplacement, $content);
                if (is_null($newContent) || $newContent === $content) {
                    $messages[] = 'Not possible to update file bazarliste.yaml (current changes were not saved) !';
                    $state = 'danger';
                } else {
                    file_put_contents(self::BAZARLISTEYAML_PATH, $newContent);
                    $content = file_get_contents(self::BAZARLISTEYAML_PATH);
                    if (!preg_match($patternAfter, $content, $matches)) {
                        $messages[] = 'The update of file bazarliste.yaml is not all right (file was not saved) !';
                        $state = 'danger';
                    } else {
                        $messages[] = 'Update of file bazarliste.yaml OK !';
                    }
                }
            }
        }
        return compact(['messages','state']);
    }
}
