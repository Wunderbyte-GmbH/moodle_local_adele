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
 * Behat steps for local_adele.
 *
 * @package    local_adele
 * @category   test
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * Steps definitions for Adele.
 */
class behat_local_adele extends behat_base {
    /**
     * Drag and drop with HTML5 data transfer.
     *
     * @When /^I drag and drop HTML5 from "(?P<source>[^"]+)" to "(?P<target>[^"]+)"$/
     *
     * @param string $source CSS selector for the draggable element.
     * @param string $target CSS selector for the drop target.
     */
    public function i_drag_and_drop_html5_from_to(string $source, string $target): void {
        $sourcesel = json_encode($source, JSON_UNESCAPED_SLASHES);
        $targetsel = json_encode($target, JSON_UNESCAPED_SLASHES);
        $script = <<<JS
            (function() {
              const source = document.querySelector($sourcesel);
              const target = document.querySelector($targetsel);
              if (!source) {
                throw new Error('HTML5 drag source not found: ' + $sourcesel);
              }
              if (!target) {
                throw new Error('HTML5 drop target not found: ' + $targetsel);
              }
              source.scrollIntoView({block: 'center', inline: 'center'});
              target.scrollIntoView({block: 'center', inline: 'center'});

              const targetRect = target.getBoundingClientRect();
              const clientX = targetRect.left + targetRect.width / 2;
              const clientY = targetRect.top + targetRect.height / 2;
              const dataTransfer = new DataTransfer();

              const dragOverTarget = document.querySelector('.learning-path-flow') || target;
              const dropTarget = target.closest('.dndflow') || target;

              const buildEvent = (type, extra = {}) => new DragEvent(type, {
                bubbles: true,
                cancelable: true,
                clientX,
                clientY,
                dataTransfer,
                ...extra,
              });

              source.dispatchEvent(buildEvent('dragstart'));
              source.dispatchEvent(buildEvent('drag'));
              document.dispatchEvent(buildEvent('dragover'));
              dragOverTarget.dispatchEvent(buildEvent('dragover'));
              dropTarget.dispatchEvent(buildEvent('dragenter'));
              dropTarget.dispatchEvent(buildEvent('dragover'));
              dropTarget.dispatchEvent(buildEvent('drop'));
              source.dispatchEvent(buildEvent('dragend'));
            })();
            JS;
        $this->getSession()->executeScript($script);
    }
}
