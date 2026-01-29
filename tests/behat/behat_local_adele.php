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

    /**
     * Zoom the Vue Flow viewport to a specific percentage (e.g. 100, 150).
     *
     * @When /^I zoom vue flow to "(?P<percent>[\d.]+)" percent$/
     *
     * @param string $percent Zoom percentage (e.g. 100 for 1.0).
     */
    public function i_zoom_vue_flow_to_percent(string $percent): void {
        $scale = ((float) $percent) / 100.0;
        $scalejson = json_encode($scale);
        $script = <<<JS
            (function() {
              const pane = document.querySelector('.vue-flow__transformationpane');
              if (!pane) {
                throw new Error('Vue Flow transformation pane not found.');
              }
              const transform = pane.style.transform || '';
              const match = /translate\\(([-\\d.]+)px,\\s*([-\\d.]+)px\\)\\s*scale\\(([-\\d.]+)\\)/.exec(transform);
              const translateX = match ? parseFloat(match[1]) : 0;
              const translateY = match ? parseFloat(match[2]) : 0;
              pane.style.transform = `translate(\${translateX}px, \${translateY}px) scale($scalejson)`;
            })();
            JS;
        $this->getSession()->executeScript($script);
    }

    /**
     * Pan the Vue Flow viewport so the element is centered.
     *
     * @When /^I pan vue flow to "(?P<target>[^"]+)"$/
     *
     * @param string $target CSS selector for the element to center.
     */
    public function i_pan_vue_flow_to(string $target): void {
        $targetsel = json_encode($target, JSON_UNESCAPED_SLASHES);
        $script = <<<JS
            (function() {
              const pane = document.querySelector('.vue-flow__transformationpane');
              const viewport = document.querySelector('.vue-flow__viewport');
              const target = document.querySelector($targetsel);
              if (!pane || !viewport) {
                throw new Error('Vue Flow viewport elements not found.');
              }
              if (!target) {
                throw new Error('Vue Flow pan target not found: ' + $targetsel);
              }
              const transform = pane.style.transform || '';
              const match = /translate\\(([-\\d.]+)px,\\s*([-\\d.]+)px\\)\\s*scale\\(([-\\d.]+)\\)/.exec(transform);
              const translateX = match ? parseFloat(match[1]) : 0;
              const translateY = match ? parseFloat(match[2]) : 0;
              const scale = match ? parseFloat(match[3]) : 1;

              const viewportRect = viewport.getBoundingClientRect();
              const viewportCenterX = viewportRect.left + viewportRect.width / 2;
              const viewportCenterY = viewportRect.top + viewportRect.height / 2;

              const nodeTransform = target.style.transform || '';
              const nodeMatch = /translate\\(([-\\d.]+)px,\\s*([-\\d.]+)px\\)/.exec(nodeTransform);
              const nodeX = nodeMatch ? parseFloat(nodeMatch[1]) : 0;
              const nodeY = nodeMatch ? parseFloat(nodeMatch[2]) : 0;

              const targetRect = target.getBoundingClientRect();
              const nodeWidth = targetRect.width / scale;
              const nodeHeight = targetRect.height / scale;
              const nodeCenterX = nodeX + nodeWidth / 2;
              const nodeCenterY = nodeY + nodeHeight / 2;

              const newTranslateX = viewportCenterX - viewportRect.left - nodeCenterX * scale;
              const newTranslateY = viewportCenterY - viewportRect.top - nodeCenterY * scale;

              pane.style.transform = `translate(\${newTranslateX}px, \${newTranslateY}px) scale(\${scale})`;
            })();
            JS;
        $this->getSession()->executeScript($script);
    }

    /**
     * Connect two Vue Flow nodes by dragging from source handle to target handle.
     *
     * @When /^I connect vue flow node "(?P<source>[^"]+)" to "(?P<target>[^"]+)"$/
     *
     * @param string $source CSS selector for the source node element.
     * @param string $target CSS selector for the target node element.
     */
    public function i_connect_vue_flow_node_to(string $source, string $target): void {
        $sourcesel = json_encode($source, JSON_UNESCAPED_SLASHES);
        $targetsel = json_encode($target, JSON_UNESCAPED_SLASHES);
        $script = <<<JS
            (function() {
              const sourceNode = document.querySelector($sourcesel);
              const targetNode = document.querySelector($targetsel);
              if (!sourceNode) {
                throw new Error('Vue Flow source node not found: ' + $sourcesel);
              }
              if (!targetNode) {
                throw new Error('Vue Flow target node not found: ' + $targetsel);
              }

              const sourceHandle = sourceNode.querySelector('.vue-flow__handle.source') || sourceNode.querySelector('.vue-flow__handle');
              const targetHandle = targetNode.querySelector('.vue-flow__handle.target') || targetNode.querySelector('.vue-flow__handle');
              if (!sourceHandle) {
                throw new Error('Vue Flow source handle not found for: ' + $sourcesel);
              }
              if (!targetHandle) {
                throw new Error('Vue Flow target handle not found for: ' + $targetsel);
              }

              const sourceRect = sourceHandle.getBoundingClientRect();
              const targetRect = targetHandle.getBoundingClientRect();
              const startX = sourceRect.left + sourceRect.width / 2;
              const startY = sourceRect.top + sourceRect.height / 2;
              const endX = targetRect.left + targetRect.width / 2;
              const endY = targetRect.top + targetRect.height / 2;

              const buildPointer = (type, x, y) => new PointerEvent(type, {
                bubbles: true,
                cancelable: true,
                clientX: x,
                clientY: y,
                button: 0,
              });

              sourceHandle.dispatchEvent(buildPointer('pointerdown', startX, startY));
              sourceHandle.dispatchEvent(
                new MouseEvent('mousedown', {bubbles: true, cancelable: true, clientX: startX, clientY: startY, button: 0})
              );
              document.dispatchEvent(buildPointer('pointermove', endX, endY));
              document.dispatchEvent(
                new MouseEvent('mousemove', {bubbles: true, cancelable: true, clientX: endX, clientY: endY, button: 0})
              );
              targetHandle.dispatchEvent(buildPointer('pointerup', endX, endY));
              targetHandle.dispatchEvent(
                new MouseEvent('mouseup', {bubbles: true, cancelable: true, clientX: endX, clientY: endY, button: 0})
              );
            })();
            JS;
        $this->getSession()->executeScript($script);
    }
}
