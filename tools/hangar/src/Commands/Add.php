<?php
declare(strict_types=1);
namespace Airship\Hangar\Commands;

use Airship\Hangar\SessionCommand;
use ParagonIE\ConstantTime\Binary;

/**
 * Class Add
 * @package Airship\Hangar\Commands
 */
class Add extends SessionCommand
{
    /**
     * @var bool
     */
    public $essential = false;

    /**
     * @var int
     */
    public $display = 3;

    /**
     * @var string
     */
    public $name = 'Add Files';

    /**
     * @var string
     */
    public $description = 'Add files/directories to the Airship update package.';

    /**
     * Fire the add command!
     *
     * @param array $args
     * @return bool
     */
    public function fire(array $args = []): bool
    {
        try {
            $this->getSession();
            $dir = $this->session['dir'] . $this->findRelativeDir();
        } catch (\Error $e) {
            echo $e->getMessage(), "\n";
            return false;
        }

        if (\count($args) === 0) {
            echo 'No file passed.', "\n";
            return false;
        }
        if (!isset($this->session['add'])) {
            echo 'Creating session data', "\n";
            $this->session['add'] = [];
        }

        $added = 0;
        foreach ($args as $file) {
            $l = Binary::safeStrlen($file) - 1;
            if ($file[$l] === DIRECTORY_SEPARATOR) {
                $file = Binary::safeSubstr($file, 0, -1);
            }
            $added += $this->addFile($file, $dir);
        }
        echo $added, ' file', ($added === 1 ? '' : 's'), ' added.', "\n";
        return true;
    }

    /**
     * Add a file or directory to the update list.
     *
     * @param string $filename
     * @param string $dir
     * @return int
     */
    protected function addFile(string $filename, string $dir = ''): int
    {
        if (!empty($dir)) {
            if ($dir[\strlen($dir) - 1] !== DIRECTORY_SEPARATOR) {
                $dir .= DIRECTORY_SEPARATOR;
            }
        }
        if (!\file_exists($filename)) {
            echo $this->c['red'], 'File not found: ', $this->c[''], $filename, "\n";
            return 0;
        }

        try {
            $path = $this->getRealPath(\realpath($dir . $filename));
        } catch (\Error $e) {
            echo $this->c['red'], $e->getMessage(), $this->c[''], "\n";
            return 0;
        }

        if (\in_array($path, $this->session['add'], true)) {
            echo $this->c['yellow'], 'File already added: ', $this->c[''], $path, "\n";
            return 0;
        }

        // Recursive adding
        if (\is_dir($path)) {
            $i = 0;
            $glob = \glob($path . DIRECTORY_SEPARATOR . '*');
            foreach ($glob as $f) {
                $i += $this->addFile($f, $path);
            }
            return $i;
        }
        $this->session['add'][] = $path;
        echo $this->c['green'], 'File added: ', $this->c[''], $path, "\n";
        return 1;
    }
}
