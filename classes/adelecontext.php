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
 * @package local_adele
 * @author Thomas Winkler
 * @copyright 2021 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_adele;

use cache_helper;
use local_adele\event\context_created;
use local_adele\event\context_updated;

use stdClass;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/adele/lib.php');

/**
 * Class catcontext
 *
 * Defines a set items and persons defined by different criteria such as:
 *  - Time (start date and end date)
 *
 * @author Georg Maißer
 * @copyright 2023 Wunderbyte GmbH
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class adelecontext {

    /**
     * $id
     *
     * @var integer
     */
    public ?int $id = null;

    /**
     * $name
     *
     * @var string
     */
    private string $name = '';

    /**
     * $description
     *
     * @var string
     */
    private string $description = '';

    /**
     * $description format
     *
     * @var integer
     */
    private int $descriptionformat = 1;

    /**
     * $json
     *
     * @var string
     */
    private string $json = '';

    /**
     * $usermodified
     *
     * @var integer
     */
    private int $usermodified = 0;

    /**
     * $timecreated
     *
     * @var integer
     */
    private int $timecreated = 0;

    /**
     * $timemodified
     *
     * @var integer
     */
    private int $timemodified = 0;

    /**
     *
     * Provide sigleton context instances
     *
     * @var array
     */
    private static $adelecontexts = [];


    /**
     * Adelecontext constructor.
     * @param stdClass $newrecord
     */
    public function __construct(stdClass $newrecord = null) {

        global $DB;

        if (empty($this->timecreated)) {
            $this->timecreated = time();
        }

        if ($newrecord && isset($newrecord->id)) {
            // If we have a new record.
            if ($oldrecord = $DB->get_record('local_adele_adelecontext', ['id' => $newrecord->id])) {
                $this->apply_values($oldrecord);
            }
        }

        if ($newrecord) {
            $this->apply_values($newrecord);
        }
    }
    /**
     * Get a context via scaleid.
     * We create scale-based contexts for uploaded items without context assigned.+
     * This is to check if a context was already created for this scale.
     *
     * @param int $scaleid
     * @return adelecontext|null
     */
    public static function get_instance(int $scaleid) {
        if (empty(self::$adelecontexts[$scaleid])) {
            return null;
        } else {
            return self::$adelecontexts[$scaleid];
        }
    }
    /**
     * Store generated context in singleton array.
     *
     * @param catcontext $catcontext
     * @param int $scaleid
     *
     * @return self
     *
     */
    public static function store_context_as_singleton(catcontext $catcontext, int $scaleid) {
        if (empty(self::$adelecontexts[$scaleid])) {
            self::$adelecontexts[$scaleid] = $catcontext;
            return true;
        } else {
            return false;
        }

    }
    /**
     * Load from DB
     *
     * @param int $contextid
     *
     * @return self
     *
     */
    public static function load_from_db(int $contextid): self {
        global $DB;
        $record = $DB->get_record('local_adele_catcontext', ['id' => $contextid]);
        return new self($record);
    }

    /**
     * Return all the values of this class as stdClass.
     *
     * @return stdClass
     */
    public function return_as_class() {

        $record = (object)[
            'name' => $this->name,
            'description' => $this->description,
            'descriptionformat' => $this->descriptionformat,
            'json' => $this->json,
            'usermodified' => $this->usermodified,
            'timecreated' => $this->timecreated,
            'timemodified' => $this->timemodified,
        ];

        // Only if the id is not empty, we add the id key.
        if (!empty($this->id)) {
            $record->id = $this->id;
        }

        return $record;
    }

    /**
     * Apply values from record.
     *
     * @param stdClass $record
     * @return void
     */
    public function apply_values(stdClass $record) {
        $this->id = $record->id ?? $this->id ?? null;
        $this->name = $record->name ?? $this->name ?? '';
        $this->description = $record->description ?? $this->description ?? '';
        $this->descriptionformat = $record->descriptionformat ?? $this->descriptionformat ?? 1;
        $this->json = $record->json ?? $this->json ?? '';
        $this->usermodified = $record->usermodified ?? $this->usermodified ?? 0;
        $this->timecreated = $record->timecreated ?? $this->timecreated ?? time();
        $this->timemodified = $record->timemodified ?? $this->timemodified ?? time();
    }

    /**
     * Get name.
     *
     * @return string
     *
     */
    public function getname():string {
        return $this->name;
    }

    /**
     * Add a default context that contains all test items.
     *
     * @return void
     *
     */
    public function create_default_context() {
        global $DB;
        $context = $DB->get_record_sql(
            "SELECT * FROM {local_adele_adelecontext} WHERE json LIKE :default",
            [
                'default' => '%"default":true%',
            ]
        );
        if (!$context) {
            $json = new stdClass();
            $json->default = true;
            $context = (object) [
                'name' => get_string('defaultcontextname', 'local_adele'),
                'description' => get_string('defaultcontextdescription', 'local_adele'),
                'descriptionformat' => 1,
                'json' => json_encode($json),
            ];
            $this->save_or_update($context);
        }
    }

    /**
     * Save or update adelecontext class.
     *
     * @param stdClass $newrecord
     * @return void
     */
    public function save_or_update(stdClass $newrecord = null) {

        global $DB, $USER;

        if ($newrecord) {
            $this->apply_values($newrecord);
        }

        $this->timemodified = time();
        $this->usermodified = $USER->id;

        if (!empty($this->id)) {
            $DB->update_record('local_adele_adelecontext', $this->return_as_class());

            // Trigger context updated event.
            $event = context_updated::create([
                'objectid' => $this->id,
                'context' => \context_system::instance(),
                'other' => [
                    'contextname' => $this->name,
                    'contextid' => $this->id,
                    'contextobjectcallback' => 'local_adele\local\classes\adelecontext::return_as_class',
                ],
                ]);
            $event->trigger();

        } else {
            $this->id = $DB->insert_record('local_adele_adelecontext', $this->return_as_class());

            // Trigger context created event.
            $event = context_created::create([
                'objectid' => $this->id,
                'context' => \context_system::instance(),
                'other' => [
                    'contextname' => $this->name,
                    'contextid' => $this->id,
                    'contextobjectcallback' => 'local_adele\local\classes\adelecontext::return_as_class',
                ],
                ]);
            $event->trigger();
        }
        cache_helper::purge_by_event('changesincatcontexts');
    }
}
