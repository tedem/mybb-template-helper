<?php

declare(strict_types=1);

namespace tedem\MyBBTemplateHelper;

define('IN_MYBB', 1);

require_once './global.php';

define('TEMPLATE_HELPER_DIR', MYBB_ROOT.'.temp/');

final class TemplateHelper
{
    /**
     * @var string The folder where the templates are stored.
     *             This property is read-only and cannot be modified after initialization.
     */
    private readonly string $templateFolder;

    /**
     * @var array An array to store the last modified times of templates.
     */
    private $lastModifiedTimes = [];

    /**
     * Constructor for the TemplateHelper class.
     *
     * @param  object  $db  Database connection object.
     */
    public function __construct(private $db)
    {
        $this->templateFolder = TEMPLATE_HELPER_DIR.'templates/';

        if (! file_exists($this->templateFolder)) {
            mkdir($this->templateFolder, 0777, true);
        }

        $this->loadLastModifiedTimes();
    }

    /**
     * Downloads the templates for the specified theme.
     *
     * This method fetches the theme properties from the database using the provided theme name,
     * and then downloads both the core templates and the theme-specific templates.
     *
     * @param  string  $themeName  The name of the theme for which to download templates.
     */
    public function downloadTemplates(string $themeName): void
    {
        echo "\033[34m[MyBB Template Helper] Downloading templates for theme '{$themeName}'...\033[0m\n";

        $query = $this->db->simple_select('themes', 'properties', "name = '{$this->db->escape_string($themeName)}'");
        $theme = $this->db->fetch_array($query);

        if (! $theme) {
            echo "\033[31m[MyBB Template Helper] Error: No theme found with name '{$themeName}'.\033[0m\n";

            return;
        }

        $properties = unserialize($theme['properties']);
        $templatesetId = $properties['templateset'];

        if (! isset($templatesetId) || empty($templatesetId)) {
            echo "\033[31m[MyBB Template Helper] Error: Templateset not found in properties.\033[0m\n";

            return;
        }

        // Download core templates
        $this->downloadTemplateGroup();

        // Download theme templates
        $this->downloadTemplateGroup($templatesetId);
    }

    /**
     * Uploads templates for a specified theme.
     *
     * This function uploads templates from the local template folder to the database for a given theme.
     * It checks if the theme exists and retrieves its properties to find the associated templateset ID.
     * It then iterates through the local templates and updates or inserts them into the database if they have changed.
     *
     * @param  string  $themeName  The name of the theme for which templates are being uploaded.
     * @param  array  $specificTemplates  An optional array of specific template names to upload. If empty, all templates will be uploaded.
     */
    public function uploadTemplates(string $themeName, array $specificTemplates = []): void
    {
        echo "\033[34m[MyBB Template Helper] Uploading templates for theme '{$themeName}'...\033[0m\n";

        $query = $this->db->simple_select('themes', 'properties', "name = '{$this->db->escape_string($themeName)}'");
        $theme = $this->db->fetch_array($query);

        if (! $theme) {
            echo "\033[31m[MyBB Template Helper] Error: No theme found with name '{$themeName}'.\033[0m\n";

            return;
        }

        $properties = unserialize($theme['properties']);
        $templatesetId = $properties['templateset'];

        if (! isset($templatesetId) || empty($templatesetId)) {
            echo "\033[31m[MyBB Template Helper] Error: Templateset not found in properties.\033[0m\n";

            return;
        }

        $localTemplates = glob($this->templateFolder.'*.tpl');

        foreach ($localTemplates as $filePath) {
            $templateName = basename($filePath, '.tpl');
            $fileContent = $this->db->escape_string(file_get_contents($filePath));
            $lastModified = filemtime($filePath);

            if ($specificTemplates !== [] && ! in_array($templateName, $specificTemplates)) {
                continue;
            }

            $query = $this->db->simple_select('templates', 'template, sid', "title = '{$this->db->escape_string($templateName)}' AND sid = {$templatesetId}");
            $template = $this->db->fetch_array($query);

            if (! isset($this->lastModifiedTimes[$templateName]) || $this->lastModifiedTimes[$templateName] !== $lastModified) {
                if ($template) {
                    $this->db->update_query('templates', ['template' => $fileContent], "title = '{$this->db->escape_string($templateName)}' AND sid = {$templatesetId}");

                    $this->lastModifiedTimes[$templateName] = $lastModified;

                    echo "\033[32m[MyBB Template Helper] Template '{$templateName}' updated in database.\033[0m\n";
                } else {
                    $mybb = new \MyBB();

                    $this->db->insert_query('templates', [
                        'title' => $templateName,
                        'template' => $fileContent,
                        'sid' => $templatesetId,
                        'version' => $mybb->version_code,
                        'dateline' => TIME_NOW,
                    ]);

                    $this->lastModifiedTimes[$templateName] = $lastModified;

                    echo "\033[32m[MyBB Template Helper] Template '{$templateName}' inserted into database.\033[0m\n";
                }
            } else {
                echo "\033[33m[MyBB Template Helper] Template '{$templateName}' has not changed. Skipping...\033[0m\n";
            }
        }

        $this->saveLastModifiedTimes();
    }

    /**
     * Downloads or updates template files from the database based on the given template set ID.
     *
     * @param  int  $sid  The template set ID. Defaults to -2, which indicates core templates.
     *
     * This function performs the following actions:
     * - Fetches templates from the database where the template set ID matches the provided $sid.
     * - For each template, it checks if the template file exists locally.
     * - If the template file does not exist, it creates the file with the content from the database.
     * - If the template file exists, it checks if the content has changed.
     * - If the content has changed and $sid is not -2, it updates the local file with the new content.
     * - It maintains a list of core templates that were downloaded or skipped.
     * - Outputs messages indicating the status of each template (downloaded, updated, or skipped).
     * - Saves the last modified times of the templates.
     */
    private function downloadTemplateGroup(int $sid = -2): void
    {
        $query = $this->db->simple_select('templates', 'title, template', "sid = {$sid}");

        $coreTemplates = [];
        $skippedCoreTemplates = [];

        while ($template = $this->db->fetch_array($query)) {
            $templateName = $template['title'];
            $templateContent = $template['template'];

            $file = $this->templateFolder.$templateName.'.tpl';
            $localContent = file_get_contents($file);

            // Download templates that don't exist
            if (! file_exists($file)) {
                file_put_contents($file, $templateContent);

                $this->lastModifiedTimes[$templateName] = filemtime($file);

                $coreTemplates[] = $templateName;

                continue;
            }

            // Check if template has changed
            if ($localContent === $templateContent) {
                if ($sid === -2) {
                    $skippedCoreTemplates[] = $templateName;
                } else {
                    echo "\033[33m[MyBB Template Helper] Template '{$templateName}' has not changed. Skipping...\033[0m\n";
                }

                continue;
            }

            // Update current template
            if ($sid !== -2) {
                file_put_contents($file, $templateContent);

                $this->lastModifiedTimes[$templateName] = filemtime($file);

                echo "\033[32m[MyBB Template Helper] Template '{$templateName}' updated.\033[0m\n";
            }
        }

        if ($coreTemplates !== [] && $sid === -2) {
            echo "\033[32m[MyBB Template Helper] Downloaded ".count($coreTemplates)." core templates.\033[0m\n";
        }

        if ($skippedCoreTemplates !== [] && $sid === -2) {
            echo "\033[33m[MyBB Template Helper] Skipped ".count($skippedCoreTemplates)." core templates.\033[0m\n";
        }

        $this->saveLastModifiedTimes();
    }

    /**
     * Load the last modified times of templates from a JSON file.
     */
    private function loadLastModifiedTimes(): void
    {
        if (file_exists(TEMPLATE_HELPER_DIR.'history.json')) {
            $this->lastModifiedTimes = json_decode(file_get_contents(TEMPLATE_HELPER_DIR.'history.json'), true);
        }
    }

    /**
     * Saves the last modified times of templates to a JSON file.
     */
    private function saveLastModifiedTimes(): void
    {
        file_put_contents(TEMPLATE_HELPER_DIR.'history.json', json_encode($this->lastModifiedTimes, JSON_PRETTY_PRINT));
    }
}
