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
 * Validate if the string does excist.
 *
 * @package     local_adele
 * @author      Jacob Viertel
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */ -->

<template>
  <div>
    <div
      class="dndflow mt-4"
    >
      <VueFlow
        :default-viewport="{ zoom: 1.0, x: 0, y: 0 }"
        :class="{ dark }"
        :fit-view-on-init="true"
        :max-zoom="1.5"
        :min-zoom="0.2"
        :zoom-on-scroll="zoomLock"
        class="learning-path-flow"
        @node-click="onNodeClickCall"
      >
        <Background
          :pattern-color="dark ? '#FFFFFB' : '#aaa'"
          gap="8"
        />
        <template #node-custom="{ data }">
          <CustomNode
            :data="data"
            :learningpath="learningpath"
            :editorview="editor_view"
          />
        </template>
        <template #node-orcourses="{ data }">
          <OrCourses
            :data="data"
            :learningpath="learningpath"
            :editorview="editor_view"
          />
        </template>
        <template #node-module="{ data }">
          <ModuleNode
          :data="data"
          :zoomstep="zoomstep"
          />
        </template>
        <template #node-expandedcourses="{ data }">
          <ExpandNodeEdit
            :data="data"
            :zoomstep="zoomstep"
          />
        </template>
      </VueFlow>
    </div>
    <p />
    <div
      class="d-flex justify-content-center"
    >
      <Controls
        :learningpath="learningpath"
        :view="true"
        @finish-edit="finishEdit"
      />
    </div>
    <p />
  </div>
</template>

<script setup>
// Import needed libraries
import { ref, watch, nextTick, onMounted } from 'vue'
import { VueFlow, useVueFlow } from '@vue-flow/core'
import Controls from './ControlsPath.vue'
import CustomNode from '../nodes/CustomNode.vue'
import { Background } from '@vue-flow/background'
import ModuleNode from '../nodes/ModuleNode.vue'
import OrCourses from '../nodes/OrCourses.vue'
import ExpandNodeEdit from '../nodes/ExpandNodeEdit.vue'
import onNodeClick from '../../composables/flowHelper/onNodeClick'


const props = defineProps({
  learningpath: {
    type: Object,
    required: true,
  }
});

// Define constants that will be referenced
const dark = ref(false)
const editor_view = ref(false)
// check the page width
const dndFlowWidth = ref(0);

const zoomSteps = [ 0.2, 0.25, 0.35, 0.55, 0.85, 1.15, 1.5]
const zoomLock = ref(false)
const zoomstep = ref(0)

// load useVueFlow properties / functions
const {
  zoomTo,
  fitView, viewport, setCenter
} = useVueFlow({
  nodes: [],
  edges: [],
})

onMounted(() => {
  const observer = new ResizeObserver(entries => {
    for (let entry of entries) {
      if (entry.target.classList.contains('dndflow')) {
        dndFlowWidth.value = entry.contentRect.width;
        break;
      }
    }
  });
  observer.observe(document.querySelector('.dndflow'));
  setTimeout(() => {
    nextTick().then(() => {
      fitView({ duration: 1000 }).then(() => {
        zoomLock.value = true
        watch(
          () => viewport.value.zoom,
          (newVal, oldVal) => {
            if (newVal && oldVal && zoomLock.value) {
              if (newVal > oldVal) {
                setZoomLevel('in')
              } else if (newVal < oldVal) {
                setZoomLevel('out')
              }
            }
          },
          { deep: true }
        );
      });
    })
  }, 300)
});

const emit = defineEmits([
  'finish-edit',
]);

const finishEdit = () => {
  emit('finish-edit');
}

const setZoomLevel = async (action) => {
  zoomLock.value = false
  let newViewport = viewport.value.zoom
  let currentStepIndex = zoomSteps.findIndex(step => newViewport < step);
  if (currentStepIndex === -1) {
    currentStepIndex = zoomSteps.length;
  }
  if (action === 'in') {
    if (currentStepIndex < zoomSteps.length) {
      newViewport = zoomSteps[currentStepIndex];
    } else {
      newViewport = zoomSteps[currentStepIndex - 2]
    }
  } else if (action === 'out') {
    if (currentStepIndex > 0) {
      newViewport = zoomSteps[currentStepIndex - 1];
    } else {
      newViewport = zoomSteps[zoomSteps.length - 2]
    }
  }
  if (newViewport != undefined) {
    zoomstep.value = newViewport
    await zoomTo(newViewport, { duration: 500}).then(() => {
      zoomLock.value = true
    })
  }
}

const onNodeClickCall = (event) => {
  zoomstep.value = onNodeClick(event, zoomLock, setCenter )
}

</script>

<style scoped>
 @import 'https://cdn.jsdelivr.net/npm/@vue-flow/core@1.26.0/dist/style.css';
 @import 'https://cdn.jsdelivr.net/npm/@vue-flow/core@1.26.0/dist/theme-default.css';
 @import 'https://cdn.jsdelivr.net/npm/@vue-flow/controls@latest/dist/style.css';
 @import 'https://cdn.jsdelivr.net/npm/@vue-flow/minimap@latest/dist/style.css';
 @import 'https://cdn.jsdelivr.net/npm/@vue-flow/node-resizer@latest/dist/style.css';

.dndflow{
  flex-direction:column;
  display:flex;height:600px
}
.dndflow aside{
  color:#fff;
  font-weight:700;
  border-right:1px solid #eee;
  padding:15px 10px;
  font-size:12px;
  -webkit-box-shadow:0px 5px 10px 0px rgba(0,0,0,.3);
  box-shadow:0 5px 10px #0000004d;
  border-top-right-radius: 1rem;
  border-bottom-right-radius: 1em;
}
.dndflow aside
.nodes>*{
  margin-bottom:10px;
  cursor:grab;
  font-weight:500;
  -webkit-box-shadow:5px 5px 10px 2px rgba(0,0,0,.25);
  box-shadow:5px 5px 10px 2px #00000040
}
.dndflow aside
.description{
  margin-bottom:10px
}
.dndflow
.vue-flow-wrapper{
  flex-grow:1;
  height:100%
}
@media screen and (min-width: 640px)
{
  .dndflow{flex-direction:row}
  .dndflow
  aside{min-width:20%}
}
@media screen and (max-width: 639px)
{
  .dndflow aside
  .nodes{
    display:flex;
    flex-direction:row;
    gap:5px
  }
}
.learning-path-flow{
  border-top-left-radius: 1rem;
  border-bottom-left-radius: 1em;
}
.learning-path-flow.dark{
  background:#4e574f;
}
</style>