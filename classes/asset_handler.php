<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Entities Class to display list of entity records.
 *
 * @package     local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele;
use context_system;
use moodle_exception;
use moodle_url;

/**
 * Class learning_path_courses
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class asset_handler {

    /**
     *
     * @var array
     */
    public static $paths = [
      'helpingslider',
      'node_background_image',

    ];

    /**
     * Start a new attempt for a user.
     * @return array
     */
    public static function get_image_paths() {
        global $CFG, $DB;
        $filepath = [
            'helpingslider' => [],
            'node_background_image' => [],
        ];
        foreach (self::$paths as $pathorigin) {
            $path = $CFG->dirroot . '/local/adele/public/' . $pathorigin . '/*';
            $filelist = glob($path);
            foreach ($filelist as $file) {
                $filepath[$pathorigin][] = [ 'path' => str_replace($CFG->dirroot, '', $file)];
            }
        }
        // Get uploaded images from mdl_files.
        $contextid = context_system::instance()->id;
        $sql = "SELECT * FROM {files}
                WHERE component = 'local_adele'
                  AND filearea = 'lp_images'
                  AND filename LIKE 'uploaded_file_lp_%'";

        $uploadedfiles = $DB->get_records_sql($sql, ['contextid' => $contextid]);
        foreach ($uploadedfiles as $file) {
            $url = moodle_url::make_pluginfile_url(
                $file->contextid,
                $file->component,
                $file->filearea,
                $file->itemid,
                $file->filepath,
                $file->filename
            );
            $filepath['node_background_image'][] = ['path' => $url->out(false)];
        }
        return $filepath;
    }

    /**
     * Start a new attempt for a user.
     * @param int $contextid
     * @param int $learningpathid
     * @param mixed $image
     * @return array
     */
    public static function set_new_image($contextid, $learningpathid, $image) {
        global $USER;

        // Decode the file data from Base64.
        $decodedfile = base64_decode($image);
        if ($decodedfile === false) {
            throw new \invalid_parameter_exception('Invalid file data');
        }

        $fs = get_file_storage();

        // Generate a temporary file path.
        $tempfile = tempnam(sys_get_temp_dir(), 'upload_');
        file_put_contents($tempfile, $decodedfile);

        // Prepare the file record.
        $filename = 'uploaded_file_lp_' . $learningpathid . '.jpg';
        $filepath = '/';

        // Check if a file already exists and delete it.
        if ($existingfile = $fs->get_file($contextid, 'local_adele', 'lp_images', $learningpathid, $filepath, $filename)) {
            $existingfile->delete();
        }

        $filerecord = [
            'contextid' => $contextid,
            'component' => 'local_adele',
            'filearea'  => 'lp_images',
            'itemid'    => $learningpathid,
            'filepath'  => '/',
            'filename'  => $filename,
            'userid'    => $USER->id,
            'license'   => 'allrightsreserved',
            'author'    => $USER->firstname . ' ' . $USER->lastname,
        ];

        // Save the file to Moodle file storage.
        $storedfile = $fs->create_file_from_pathname($filerecord, $tempfile);
        // Clean up the temporary file.
        unlink($tempfile);
        if ($storedfile) {
            $url = moodle_url::make_pluginfile_url(
                $storedfile->get_contextid(),
                $storedfile->get_component(),
                $storedfile->get_filearea(),
                $storedfile->get_itemid(),
                $storedfile->get_filepath(),
                $storedfile->get_filename()
            )->out(false);
            return ['status' => 'success', 'filename' => $url];
        } else {
            throw new moodle_exception('File upload failed');
        }
    }
}
