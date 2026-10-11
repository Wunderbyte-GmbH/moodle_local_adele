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

<script setup>
// Import needed libraries
import { Handle, Position } from '@vue-flow/core'
import absUrl from '../../composables/absUrl';
import { computed, ref, onMounted } from 'vue';
import { useStore } from 'vuex';
import CompletionOutPutItem from '../completion/CompletionOutPutItem.vue'
import RestrictionOutPutItem from '../restriction/RestrictionOutPutItem.vue'
import UserInformation from '../nodes_items/UserInformation.vue';
import ProgressBar from '../nodes_items/ProgressBar.vue';
import NodeInformation from '../nodes_items/NodeInformation.vue';
import truncatedText from '../../composables/nodesHelper/truncatedText';
import MasterConditions from '../nodes_items/MasterConditions.vue';
import { useStatusMessage } from '../../composables/useStatusMessage';
import * as nodeColors from '../../config/nodeColors';
import { useNodeStatus } from '../../composables/useNodeStatus';

// Load Store
const store = useStore();
// Machine-readable identity and state of this node (#574) and its accessible
// name (#575 B1). All three come from useNodeStatus, the single derivation
// shared with the icon the sighted user sees; role="group" is what makes the
// name reachable for an assistive technology - aria-label on a bare div is
// not exposed.
const { status: nodeStatus, accessibleName: nodeAccessibleName } = useNodeStatus(
  computed(() => props.data),
  computed(() => store.state.strings)
);

const date = ref({})
const includedCourses = ref([])
const parentnode = ref({})
const props = defineProps({
  data: {
    type: Object,
    required: true,
  },
  learningpath: {
    type: Object,
    required: true,
  },
  zoomstep: {
    type: Number,
    required: true,
  },
});

const active = ref(false)
const startanimation = ref(true);

const handleNodeClick = () => {
  startanimation.value = false;
};

onMounted(() => {
  const userpath = props.learningpath
  userpath.json.tree.nodes.forEach((node) => {
    if (props.data.node_id == node.id) {
      parentnode.value = node
      if (node.restriction && node.restriction.nodes) {
        node.restriction.nodes.forEach((restrictionnode) => {
          if (restrictionnode.data.label == 'timed') {
            date.value = restrictionnode.data.value
          }
          // Only a real timed / timed_duration restriction shows the timed "ring"
          // affordance around the play button. (The backend timed restrictioncriteria
          // object also holds entries for non-timed restriction nodes, so its
          // non-emptiness cannot be used to detect an actual timed condition - #445 follow-up.)
          if (
            restrictionnode.data.label == 'timed' ||
            restrictionnode.data.label == 'timed_duration'
          ) {
            hasTimedCondition.value = true
          }
        })
      }
      if (node.parentCourse.includes('starting_node')) {
        isParentNode.value = true;
      }
    }
  })

  if (props.data.course_node_id && store.state.availablecourses) {
    props.data.course_node_id.forEach((course_id) => {
      const course = store.state.availablecourses.find(course => course.course_node_id[0] === course_id)
      if (course) {
        includedCourses.value.push({
          id: course.course_node_id[0],
          name: course.fullname,
        })
      }
    })
  }
  if (store.state.view == 'teacher') {
    active.value = true
  } else if (props.data.completion.singlerestrictionnode && props.data.completion.singlerestrictionnode.length == 0) {
    active.value = true
  } else if (props.data.completion.restrictionnode && props.data.completion.restrictionnode.valid) {
    active.value = true
  } else if (props.data.completion.feedback.status == 'accessible') {
    active.value = true
  }

  // No lock->play attention animation any more (#570): the flipping icon (and
  // its flipping tooltip) made accessible nodes read as locked. An accessible
  // node shows a steady play symbol from the first paint on.
  if (
    props.data.completion.feedback &&
    props.data.completion.feedback.status !== 'closed' &&
    props.data.completion.feedback.status !== 'not_accessible' &&
    props.data.completion.feedback.status_restriction !== 'after'
  ) {
    iconClass.value = 'fa-play';
  }
})

const { statusMessage } = useStatusMessage(computed(() => props.data));

const customNodeEdit = ref(null);
const emit = defineEmits([

  'zoomOnParent'
]);

const zoomOnParent = (payload = {}) => {
  // Default to opening for backwards compatibility with callers that emit no state.
  const open = payload.open !== false;
  if (customNodeEdit.value) {
    // Walk up to this node's vue-flow wrapper and raise ONLY this node while its
    // feedback dropdown is open, so the dropdown paints above the neighbouring
    // nodes; clear it again on close so vue-flow takes back control.
    //
    // We must NOT bump the *sibling* nodes' z-index (the previous code set every
    // sibling to '10'). Doing so pulled the learning-module "background"
    // rectangle node (e.g. `0_module`, 540x500) up to the same z-index as the
    // course nodes sitting on top of it; being later in DOM order it then
    // painted over them and swallowed their clicks. That was the real cause of
    // the stack/"Lernpaket" click-blocking (issues #425 / #436 / #453).
    let parentElement = customNodeEdit.value.parentElement;
    while (parentElement) {
      if (parentElement.classList.contains('vue-flow__node')) {
        parentElement.style.zIndex = open ? '1001' : '';
        break; // Exit once the desired parent is styled
      }
      parentElement = parentElement.parentElement;
    }
  }
  emit('zoomOnParent', {});
}
const cover_image = computed(() => absUrl(get_cover_image(props.data), store.state.wwwroot));

const get_cover_image = (data) => {
  if (data.selected_course_image) {
    return data.selected_course_image
  } else if (data.selected_image) {
    return data.selected_image
  } else if (data.image_paths) {
    return data.image_paths
  } else if (store.state.lpuserpathrelation.image) {
    return store.state.lpuserpathrelation.image
  }
}

const cardBackgroundColor = computed(() => {
    return { backgroundColor: nodeColors.cardBackgroundColor }; 
});

const nodeBackgroundColor = computed(() => {
    return { backgroundColor: nodeColors.nodeBackgroundColorDefault };
});

// Header background color based on statusMessage
const headerBackgroundColor = computed(() => {
  let color;
  switch (statusMessage.value) {
    case '0':
      color = nodeColors.headerBackgroundColorCase0;
      break;
    case 'a1':
      color = nodeColors.headerBackgroundColorCaseA1;
      break;
    case 'a2':
      color = nodeColors.headerBackgroundColorCaseA2;
      break;
    case 'b':
      color = nodeColors.headerBackgroundColorCaseB;
      break;
    case 'c':
      color = nodeColors.headerBackgroundColorCaseC;
      break;
    case 'd':
      color = nodeColors.headerBackgroundColorCaseD;
      break;
    case 'e':
      color = nodeColors.headerBackgroundColorCaseE;
      break;
    case 'f':
      color = nodeColors.headerBackgroundColorCaseF;
      break;
    default:
      color = nodeColors.headerBackgroundColorCaseDefault;
      break;
  }
  return { backgroundColor: color, color: '#fff' };
});

// Connection handles
const handleStyle = computed(() => ({ backgroundColor: props.data.color, filter: 'invert(100%)', width: '10px', height: '10px' }))

const isParentNode = ref(false);

const parentStyle = {
  borderColor: 'darkgrey',
  borderWidth: '2px',
};

const goToCourse = () => {
  if (iconClass.value == 'fa-play') {
    let course_link = store.state.wwwroot + '/course/view.php?id=' + props.data.course_node_id
    window.open(course_link, '_blank');
  }
}
const iconClass = ref('fa-lock');
const hasTimedCondition = ref(false);

// The play button only navigates when the node is reachable (fa-play); when it is
// locked (fa-lock) the hover hint must say "not accessible", not "go to course" (#462).
const courseLinkTitle = computed(() =>
  iconClass.value === 'fa-play'
    ? store.state.strings.go_to_course
    : store.state.strings.node_not_accessible
);
</script>

<template>
  <div
    ref="customNodeEdit"
    role="group"
    :aria-label="nodeAccessibleName"
    :data-testid="'learningpath-node-' + data.node_id"
    :data-status="nodeStatus"
    @click="handleNodeClick"
  >
    <div v-if="zoomstep != '0.2'" class="card" :style="[{ minHeight: '200px', width: '400px' }, parentStyle, cardBackgroundColor]">
      <div class="card-header text-center" :style="headerBackgroundColor">
        <NodeInformation v-if="store.state.feedbacksettings.show_info" :data :parentnode :startanimation :status="statusMessage" />
        <div class="row align-items-center">
          <div class="col">
            <h5 style="color: #000;">
              {{ truncatedText(data.fullname || store.state.strings.nodes_collection, 45) }}
            </h5>
          </div>
        </div>
      </div>
      <div class="card-body" :style="nodeBackgroundColor">
        <div>
          <div class="card-img dashboard-card-img mb-2" :style="{
            height: '10rem',
            backgroundImage: cover_image ? 'url(' + cover_image + ')' : 'none',
            backgroundSize: 'cover',
            backgroundPosition: 'center',
            backgroundColor: cover_image ? '' : '#cccccc'
          }">
            <div class="overlay">
              <button class="icon-link" :title="courseLinkTitle" @click="goToCourse">
                <div
                  :class="{ 'icon-with-ring': hasTimedCondition }"
                  :style="hasTimedCondition ? { backgroundImage: 'url(' + store.state.wwwroot + '/local/adele/public/ring.png)' } : {}"
                >
                  <i :class="['fa', iconClass]" />
                </div>
              </button>
            </div>
          </div>
          <div class="row mb-2">
            <div class="col-12" style="display: flex; justify-content: end;">
              <ProgressBar :progress="data.progress" :status="statusMessage" />
            </div>
            <div v-if="store.state.view == 'teacher' && store.state.canedit !== false && data.manualrestriction">
              <RestrictionOutPutItem :data="data" />
            </div>
            <div v-if="store.state.view == 'teacher' && store.state.canedit !== false && data.manualcompletion">
              <CompletionOutPutItem :data="data" />
            </div>
            <MasterConditions v-if="store.state.view == 'teacher' && store.state.canedit !== false" class="col-12" :data="data" />
          </div>
        </div>
      </div>
      <div v-if="data" class="card-footer">
        <UserInformation v-if="store.state.feedbacksettings.show_feedback" :data="data" @focusChanged="zoomOnParent" :status="statusMessage" />
      </div>
    </div>
    <div v-else class="card" :style="[{ minHeight: '300px', width: '400px' }, parentStyle]">
      <div class="card-body card-body-outer" :style="[nodeBackgroundColor]">
        {{ truncatedText(data.fullname || store.state.strings.nodes_collection, 28) }}
      </div>
    </div>
    <Handle v-if="store.state.view != 'teacher' && store.state.view != 'student'" id="target" type="target"
      :position="Position.Top" :style="handleStyle" />
    <Handle v-if="store.state.view != 'teacher' && store.state.view != 'student'" id="source" type="source"
      :position="Position.Bottom" :style="handleStyle" />
  </div>
</template>

<style scoped>
.card-body-outer {
  display: flex;
  justify-content: center;
  align-items: center;
  text-align: center;
  min-height: 200px;
  padding: 1rem;
  font-size: clamp(40px, 2.8vw, 64px);
  word-break: break-word;
  overflow-wrap: break-word;
  white-space: normal;
  text-overflow: ellipsis;
  font-weight: bold;
  hyphens: auto;
}

.overlay {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  background-color: rgba(0, 0, 0, 0.4);
  /* Semi-transparent gray */
  display: flex;
  justify-content: center;
  align-items: center;
  width: 70%;
  /* Adjust width as needed */
  height: 50%;
  /* Adjust height as needed */
  border-radius: 15px;
  /* Rounded edges */
}

.dashboard-card-img {
  position: relative;
}

.icon-link {
  border: none;
  background: none;
  color: white;
  font-size: 30px;
  cursor: pointer;
  padding: 10px;
  margin: 0 15px;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
}

.icon-link:hover {
  color: lightgray;
  /* Hover effect */
}

.inactive-node {
  pointer-events: none;
  opacity: 0.5;
}

.table-hover tbody tr:hover {
  background-color: #f5f5f5;

}

.table-container {
  width: 300px;
  position: absolute;
}

.table-container-left {
  transform: translate(-50%, 0);
}

.fancy-table {
  border-radius: 10px;
  /* Rounded corners */
}

.fancy-table thead th {
  background-color: #3498db;
  /* Header background color */
  color: #fff;
  /* Header text color */
}

.fancy-table tbody {
  background-color: #ecf0f1;
  /* Body background color */
}

.fancy-table tbody tr:nth-child(odd) {
  background-color: #d1d1d1;
  /* Alternate row background color */
}

.fancy-table tbody tr:hover {
  background-color: #bdc3c7;
  /* Hovered row background color */
}

.starting-node {
  font-size: 20px;
  /* Adjust the font size as needed */
  font-weight: bold;
  /* Make the text bold */
  display: flex;
  /* Align icon and text horizontally */
  align-items: center;
  /* Center items vertically */
}

.icon-with-ring {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 72px;
  height: 72px;
  background-repeat: no-repeat;
  background-size: contain;
  background-position: center;
}
</style>