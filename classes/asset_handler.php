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
                // Absolute URL (like the uploaded pluginfile URLs below): a root-relative
                // path 404s on sub-directory installs, e.g. localhost/moodle03 (#459).
                $filepath[$pathorigin][] = [ 'path' => $CFG->wwwroot . str_replace($CFG->dirroot, '', $file)];
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
     *
     * Validates the base64 payload (size/MIME/image) before storing it, and
     * uses get_area_files() to find and remove ALL previous files for this
     * learning path independent of the exact filename, so old files do not
     * accumulate.
     *
     * @param int $contextid
     * @param int $learningpathid
     * @param mixed $image
     * @return array
     */
    public static function set_new_image($contextid, $learningpathid, $image) {
        global $USER;

        // Strict mode: reject malformed base64 instead of silently decoding
        // a partial/garbled result.
        $decodedfile = base64_decode($image, true);
        if ($decodedfile === false) {
            throw new \invalid_parameter_exception('Invalid file data');
        }

        $maxbytes = 5 * 1024 * 1024;
        if (strlen($decodedfile) > $maxbytes) {
            throw new \invalid_parameter_exception('Image exceeds the maximum allowed size');
        }

        $imageinfo = @getimagesizefromstring($decodedfile);
        $allowedmimes = ['image/jpeg', 'image/png', 'image/webp'];
        if ($imageinfo === false || empty($imageinfo['mime']) || !in_array($imageinfo['mime'], $allowedmimes, true)) {
            throw new \invalid_parameter_exception('The uploaded data is not a supported image');
        }

        $fs = get_file_storage();

        // Generate a temporary file path.
        $tempfile = tempnam(sys_get_temp_dir(), 'upload_');

        try {
            file_put_contents($tempfile, $decodedfile);

            // Remove every previous file for this learning path, regardless
            // of the exact (timestamped) filename it was stored under.
            $oldfiles = $fs->get_area_files($contextid, 'local_adele', 'lp_images', $learningpathid, 'filename', false);
            foreach ($oldfiles as $oldfile) {
                $oldfile->delete();
            }

            $filename = 'uploaded_file_lp_' . $learningpathid . '.jpg';
            $filerecord = [
                'contextid' => $contextid,
                'component' => 'local_adele',
                'filearea'  => 'lp_images',
                'itemid'    => $learningpathid,
                'filepath'  => '/',
                'filename'  => $filename . (string) \core\di::get(\core\clock::class)->time(),
                'userid'    => $USER->id,
                'license'   => 'allrightsreserved',
                'author'    => $USER->firstname . ' ' . $USER->lastname,
            ];

            // Save the file to Moodle file storage.
            $storedfile = $fs->create_file_from_pathname($filerecord, $tempfile);
        } finally {
            @unlink($tempfile);
        }

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
