<!-- // This file is part of Moodle - http://moodle.org/
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
 * Step-by-step introduction tutorial for building a learning path (Part A) and
 * adding it to a course (Part B). Localizable: every caption and marker label
 * comes from the local_adele language pack, the imagery from public/tutorial.
 *
 * @package     local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */ -->

<template>
  <div>
    <div
      id="helpingSlider"
      tabindex="-1"
      role="dialog"
      class="modal fade"
      aria-labelledby="helpingSliderModal"
      aria-hidden="true"
    >
      <div
        class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"
        role="document"
      >
        <div class="modal-content adele-tutorial">
          <!-- Header -->
          <div
            class="modal-header text-white"
            :style="{ backgroundColor: store.state.strings.DARK_GREEN }"
          >
            <h5 class="modal-title">
              {{ s.tutorial_title }}
            </h5>
            <button
              type="button"
              class="close text-white"
              data-dismiss="modal"
              aria-label="Close"
            >
              <span aria-hidden="true">&times;</span>
            </button>
          </div>

          <!-- Body -->
          <div
            class="modal-body"
            :style="{ backgroundColor: store.state.strings.LIGHT_GRAY }"
          >
            <!-- Part switch -->
            <div class="adele-tutorial-tabs">
              <button
                type="button"
                class="btn adele-tab"
                :class="currentStep.part === 'A' ? 'adele-tab-active' : ''"
                :style="currentStep.part === 'A' ? activeTabStyle : {}"
                @click="goToPart('A')"
              >
                {{ s.tutorial_part_a }}
              </button>
              <button
                type="button"
                class="btn adele-tab"
                :class="currentStep.part === 'B' ? 'adele-tab-active' : ''"
                :style="currentStep.part === 'B' ? activeTabStyle : {}"
                @click="goToPart('B')"
              >
                {{ s.tutorial_part_b }}
              </button>
            </div>

            <!-- Caption -->
            <div class="adele-tutorial-caption">
              <span
                class="adele-step-badge"
                :style="{ backgroundColor: store.state.strings.DARK_GREEN }"
              >{{ index + 1 }}</span>
              <div>
                <h4 class="adele-step-title">{{ s[currentStep.title] }}</h4>
                <p class="adele-step-text">{{ s[currentStep.text] }}</p>
              </div>
            </div>

            <!-- OR / AND logic diagram (no screenshot for this step) -->
            <div v-if="currentStep.diagram" class="adele-tutorial-stage">
              <div class="adele-logic">
                <div class="adele-logic-col">
                  <div
                    class="adele-logic-node"
                    :style="{ backgroundColor: store.state.strings.DARK_GREEN }"
                  >{{ s.tutorial_logic_node }}</div>
                  <div class="adele-logic-row">
                    <div class="adele-logic-cond">{{ s.tutorial_logic_cond }} 1</div>
                    <div
                      class="adele-logic-op"
                      :style="{ color: store.state.strings.DARK_ORANGE }"
                    >{{ s.tutorial_op_or }}</div>
                    <div class="adele-logic-cond">{{ s.tutorial_logic_cond }} 2</div>
                  </div>
                  <div class="adele-logic-caption">{{ s.tutorial_logic_or }}</div>
                </div>

                <div class="adele-logic-col">
                  <div
                    class="adele-logic-node"
                    :style="{ backgroundColor: store.state.strings.DARK_GREEN }"
                  >{{ s.tutorial_logic_node }}</div>
                  <div class="adele-logic-stack">
                    <div class="adele-logic-cond">{{ s.tutorial_logic_cond }} 1</div>
                    <div
                      class="adele-logic-op"
                      :style="{ color: store.state.strings.DARK_GREEN }"
                    >{{ s.tutorial_op_and }}</div>
                    <div class="adele-logic-cond">{{ s.tutorial_logic_cond }} 2</div>
                  </div>
                  <div class="adele-logic-caption">{{ s.tutorial_logic_and }}</div>
                </div>
              </div>
            </div>

            <!-- Screenshot with markers -->
            <div v-else class="adele-tutorial-stage">
              <div class="adele-img-wrap">
                <img
                  :src="imageBase + currentStep.img"
                  class="adele-img"
                  :alt="s[currentStep.title]"
                >
                <span
                  v-for="(marker, mi) in currentStep.markers"
                  :key="mi"
                  class="adele-marker"
                  :style="{ top: marker.y + '%', left: marker.x + '%' }"
                >
                  <span
                    class="adele-marker-dot"
                    :style="{ backgroundColor: store.state.strings.DARK_ORANGE }"
                  >{{ mi + 1 }}</span>
                  <span v-if="!currentStep.legend" class="adele-marker-label">{{ s[marker.label] }}</span>
                </span>
              </div>
            </div>

            <!-- Legend (used when markers sit too close for inline labels) -->
            <div v-if="currentStep.legend" class="adele-legend">
              <span
                v-for="(marker, mi) in currentStep.markers"
                :key="mi"
                class="adele-legend-item"
              >
                <span
                  class="adele-marker-dot adele-legend-dot"
                  :style="{ backgroundColor: store.state.strings.DARK_ORANGE }"
                >{{ mi + 1 }}</span>
                {{ s[marker.label] }}
              </span>
            </div>
          </div>

          <!-- Footer -->
          <div
            class="modal-footer adele-tutorial-footer"
            :style="{ backgroundColor: store.state.strings.DARK_GREEN }"
          >
            <button
              type="button"
              class="btn btn-light"
              :disabled="index === 0"
              @click="prev"
            >
              &lsaquo; {{ s.tutorial_back }}
            </button>

            <div class="adele-dots">
              <span
                v-for="(step, di) in steps"
                :key="di"
                class="adele-dot"
                :class="di === index ? 'adele-dot-active' : ''"
                :title="s[step.title]"
                @click="index = di"
              />
            </div>

            <button
              v-if="index < steps.length - 1"
              type="button"
              class="btn btn-light"
              @click="next"
            >
              {{ s.tutorial_next }} &rsaquo;
            </button>
            <button
              v-else
              type="button"
              class="btn btn-light"
              data-dismiss="modal"
            >
              {{ s.tutorial_finish }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useStore } from 'vuex';

const store = useStore();
const s = computed(() => store.state.strings);

const imageBase = '/local/adele/public/tutorial/';

// Each step: which part it belongs to, the screenshot, caption string keys and
// the markers (position in % of the image plus a label string key).
const steps = [
  { part: 'A', img: 'a1_overview.png', title: 'tutorial_a1_title', text: 'tutorial_a1_text',
    markers: [{ x: 12, y: 33, label: 'tutorial_mark_a1' }] },
  { part: 'A', img: 'a2_details.png', title: 'tutorial_a2_title', text: 'tutorial_a2_text',
    markers: [{ x: 43, y: 17, label: 'tutorial_mark_a2' }] },
  { part: 'A', img: 'a3_courses.png', title: 'tutorial_a3_title', text: 'tutorial_a3_text',
    markers: [{ x: 73, y: 34, label: 'tutorial_mark_a3a' }, { x: 29, y: 41, label: 'tutorial_mark_a3b' }] },
  { part: 'A', img: 'a4_connected.png', title: 'tutorial_a4_title', text: 'tutorial_a4_text',
    markers: [{ x: 37, y: 45, label: 'tutorial_mark_a4' }] },
  { part: 'A', img: 'a_node_buttons.png', title: 'tutorial_nb_title', text: 'tutorial_nb_text', legend: true,
    markers: [{ x: 39, y: 61, label: 'tutorial_mark_btn_lock' }, { x: 56, y: 61, label: 'tutorial_mark_btn_tasks' },
      { x: 73, y: 61, label: 'tutorial_mark_btn_pencil' }, { x: 93, y: 9, label: 'tutorial_mark_btn_delete' }] },
  { part: 'A', img: 'a5_restriction.png', title: 'tutorial_a5_title', text: 'tutorial_a5_text',
    markers: [{ x: 83, y: 33, label: 'tutorial_mark_a5a' }, { x: 56, y: 75, label: 'tutorial_mark_a5b' }] },
  { part: 'A', img: 'a6_restriction_added.png', title: 'tutorial_a6_title', text: 'tutorial_a6_text',
    markers: [{ x: 83, y: 33, label: 'tutorial_mark_a6a' }, { x: 41, y: 41, label: 'tutorial_mark_a6b' },
      { x: 41, y: 54, label: 'tutorial_mark_a6c' }] },
  { part: 'A', diagram: true, title: 'tutorial_logic_title', text: 'tutorial_logic_text' },
  { part: 'A', img: 'a8_completion_added.png', title: 'tutorial_a7_title', text: 'tutorial_a7_text',
    markers: [{ x: 83, y: 42, label: 'tutorial_mark_a7a' }, { x: 41, y: 72, label: 'tutorial_mark_a7b' }] },
  { part: 'A', img: 'a9_saved.png', title: 'tutorial_a8_title', text: 'tutorial_a8_text',
    markers: [{ x: 15, y: 52, label: 'tutorial_mark_a8' }] },
  { part: 'B', img: 'b1_settings.png', title: 'tutorial_b1_title', text: 'tutorial_b1_text',
    markers: [{ x: 60, y: 27, label: 'tutorial_mark_b1' }] },
  { part: 'B', img: 'b2_settings_filled.png', title: 'tutorial_b2_title', text: 'tutorial_b2_text',
    markers: [{ x: 43, y: 56, label: 'tutorial_mark_b2a' }, { x: 53, y: 71, label: 'tutorial_mark_b2b' }] },
  { part: 'B', img: 'b3_course_view.png', title: 'tutorial_b3_title', text: 'tutorial_b3_text',
    markers: [{ x: 40, y: 62, label: 'tutorial_mark_b3a' }, { x: 26, y: 82, label: 'tutorial_mark_b3b' }] },
];

const index = ref(0);
const currentStep = computed(() => steps[index.value]);

const activeTabStyle = computed(() => ({
  backgroundColor: store.state.strings.DARK_GREEN,
  borderColor: store.state.strings.DARK_GREEN,
  color: '#fff',
}));

function next() {
  if (index.value < steps.length - 1) {
    index.value++;
  }
}
function prev() {
  if (index.value > 0) {
    index.value--;
  }
}
function goToPart(part) {
  const target = steps.findIndex((step) => step.part === part);
  if (target !== -1) {
    index.value = target;
  }
}

// Reset to the first step every time the modal is opened.
onMounted(() => {
  const modal = document.getElementById('helpingSlider');
  if (modal) {
    modal.addEventListener('show.bs.modal', () => {
      index.value = 0;
    });
  }
});
</script>

<style scoped>
.adele-tutorial-tabs {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 1rem;
  flex-wrap: wrap;
}
.adele-tab {
  border: 1px solid #ced4da;
  background-color: #fff;
  font-weight: 600;
  border-radius: 2rem;
  padding: 0.35rem 1.1rem;
}
.adele-tab-active {
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
}
.adele-tutorial-caption {
  display: flex;
  align-items: flex-start;
  gap: 0.85rem;
  margin-bottom: 1rem;
}
.adele-step-badge {
  flex: 0 0 auto;
  width: 2.2rem;
  height: 2.2rem;
  border-radius: 50%;
  color: #fff;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.1rem;
}
.adele-step-title {
  margin: 0 0 0.25rem 0;
  font-weight: 700;
}
.adele-step-text {
  margin: 0;
  color: #333;
}
.adele-tutorial-stage {
  display: flex;
  justify-content: center;
}
.adele-img-wrap {
  position: relative;
  display: inline-block;
  max-width: 100%;
  border: 1px solid #d6d6d6;
  border-radius: 0.5rem;
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.18);
  background-color: #fff;
  overflow: hidden;
}
.adele-img {
  display: block;
  width: auto;
  max-width: 100%;
  max-height: 52vh;
}
.adele-marker {
  position: absolute;
  transform: translate(-50%, -50%);
  display: flex;
  align-items: center;
  pointer-events: none;
  z-index: 2;
}
.adele-marker-dot {
  width: 1.7rem;
  height: 1.7rem;
  border-radius: 50%;
  color: #fff;
  font-weight: 700;
  font-size: 0.95rem;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 2px solid #fff;
  box-shadow: 0 0 0 2px rgba(0, 0, 0, 0.25), 0 2px 6px rgba(0, 0, 0, 0.3);
  animation: adele-pulse 2s infinite;
}
.adele-marker-label {
  margin-left: 0.4rem;
  background-color: rgba(0, 0, 0, 0.78);
  color: #fff;
  font-size: 0.8rem;
  font-weight: 600;
  padding: 0.15rem 0.5rem;
  border-radius: 0.35rem;
  white-space: nowrap;
}
@keyframes adele-pulse {
  0% { box-shadow: 0 0 0 2px rgba(0, 0, 0, 0.25), 0 0 0 0 rgba(223, 132, 59, 0.6); }
  70% { box-shadow: 0 0 0 2px rgba(0, 0, 0, 0.25), 0 0 0 12px rgba(223, 132, 59, 0); }
  100% { box-shadow: 0 0 0 2px rgba(0, 0, 0, 0.25), 0 0 0 0 rgba(223, 132, 59, 0); }
}
.adele-logic {
  display: flex;
  gap: 3rem;
  flex-wrap: wrap;
  justify-content: center;
  padding: 1.5rem 1rem 2rem;
}
.adele-logic-col {
  display: flex;
  flex-direction: column;
  align-items: center;
}
.adele-logic-node {
  color: #fff;
  font-weight: 700;
  padding: 0.5rem 1.4rem;
  border-radius: 0.4rem;
  margin-bottom: 1.4rem;
  position: relative;
}
.adele-logic-node::after {
  content: '';
  position: absolute;
  bottom: -1.4rem;
  left: 50%;
  width: 2px;
  height: 1.4rem;
  background-color: #adb5bd;
}
.adele-logic-row {
  display: flex;
  align-items: center;
  gap: 0.6rem;
}
.adele-logic-stack {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.6rem;
}
.adele-logic-cond {
  background-color: #fff;
  border: 1px solid #ced4da;
  border-radius: 0.4rem;
  padding: 0.55rem 1rem;
  font-weight: 600;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.12);
  white-space: nowrap;
}
.adele-logic-op {
  font-weight: 800;
  font-size: 1.05rem;
}
.adele-logic-caption {
  margin-top: 1.1rem;
  font-weight: 600;
  color: #333;
  text-align: center;
}
.adele-legend {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 0.5rem 1.4rem;
  margin-top: 1rem;
}
.adele-legend-item {
  display: flex;
  align-items: center;
  gap: 0.45rem;
  font-weight: 600;
  color: #333;
}
.adele-legend-dot {
  width: 1.5rem;
  height: 1.5rem;
  font-size: 0.85rem;
  animation: none;
  box-shadow: none;
}
.adele-tutorial-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.adele-dots {
  display: flex;
  gap: 0.4rem;
  flex: 1 1 auto;
  justify-content: center;
  flex-wrap: wrap;
}
.adele-dot {
  width: 0.7rem;
  height: 0.7rem;
  border-radius: 50%;
  background-color: rgba(255, 255, 255, 0.45);
  cursor: pointer;
}
.adele-dot-active {
  background-color: #fff;
  transform: scale(1.25);
}
</style>
