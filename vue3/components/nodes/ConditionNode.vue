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
import { Handle, Position, useVueFlow } from '@vue-flow/core'
import RestrictionItem from '../restriction/RestrictionItem.vue'
import CompletionItem from '../completion/CompletionItem.vue'
import { computed, onMounted, ref } from 'vue';

const { nodes, edges, removeNodes, findNode, addEdges } = useVueFlow()

// Connection handles
const handleStyle = computed(() => ({ backgroundColor: props.data.color, filter: 'invert(100%)', width: '10px', height: '10px'}))

const props = defineProps({
  data: {
    type: Object,
    required: true,
  },
  type: {
    type: String,
    default: null
  },
  learningpath: {
    type: Object,
    required: true,
  }
});

const data_visibility = ref({});
const emit = defineEmits([
  'updateVisibility',
])

onMounted(() => {
  data_visibility.value = JSON.parse(JSON.stringify(props.data))
})

const toggleVisibility = () => {
  data_visibility.value.visibility = !data_visibility.value.visibility;
  emit('updateVisibility', data_visibility.value)
};

const deleteCondition = () => {
  replaceNode() 
}

const getVerticalEdges = (id, edges) => {
  let count_vertical_edges = 0
  edges.forEach(edge => {
    if (
      (
        edge.data.type == 'additional' ||
        Object.keys(edge.data).length == 0
      ) &&
      (
        edge.source == id ||
        edge.target == id
      )
    ) {
      count_vertical_edges ++
    }
  })
  return count_vertical_edges
}

const replaceNode = () => {
  const deletedNode = findNode(props.data.node_id)
  const copieEdges = JSON.parse(JSON.stringify(edges.value))
  let edgesVertical = getVerticalEdges(deletedNode.id, edges.value)
  if (edgesVertical == 0) {
    let singleNode = findNode(deletedNode.id)
    singleNode.deletable = true
    removeNodes([singleNode.id])
    shiftLeft(deletedNode, copieEdges)
  } else {
    edges.value.forEach(edge => {
      if (
        edge.targetHandle == 'target_and' &&
        edge.source == props.data.node_id
      ) {
        let prevNode = null
        let currentNode = findNode(edge.source)
        let nextNode = findNode(edge.target)
        while(nextNode) {
          prevNode = currentNode
          nextNode.data.node_id = currentNode.id
          currentNode.data = nextNode.data
          currentNode = nextNode
          nextNode = findNode(currentNode.childCondition[0])
        }
        if (currentNode != undefined) {
          currentNode.deletable = true
          removeNodes([currentNode.id])
          if (prevNode.childCondition.length > 0) {
              let new_children = []
              prevNode.childCondition.forEach((childNode) => {
                if (findNode(childNode)) {
                  new_children.push(childNode)
                }
              })
              prevNode.childCondition = new_children
          }
        }
      } else if (
        edge.sourceHandle == 'target_and' &&
        edge.source == props.data.node_id &&
        edge.target.includes('_feedback') && (
          deletedNode.childCondition.length == 0 ||
          (
            deletedNode.childCondition[0].includes('_feedback') &&
            deletedNode.childCondition.length == 1
          )
        )
      ) {
        let currentNode = findNode(edge.source)
        currentNode.deletable = true
        let feedbackNode = findNode(edge.target)
        feedbackNode.deletable = true
        removeNodes([currentNode.id, feedbackNode.id])
        shiftLeft(deletedNode, copieEdges)
      } else if (
        edge.targetHandle == 'target_and' &&
        edge.target == props.data.node_id  && 
        deletedNode.childCondition.length == 0
      ) {
        let currentNode = findNode(props.data.node_id)
        currentNode.deletable = true
        removeNodes([currentNode.id])
        let parentNode = findNode(edge.source)
        parentNode.childCondition = []
      }
    })
  }
  emit('updateVisibility', data_visibility.value)
};

const shiftLeft = (deletedNode, edges) => {
  const newEdge = {
    id: null,
    source: null,
    sourceHandle: 'source_or',
    target: null,
    targetHandle: 'target_or',
    type: 'condition',
    data: {
      type: 'disjunctional',
      text: 'OR',
    },
  };
  nodes.value.forEach(node => {
    if (node.position.x > deletedNode.position.x) {
      node.position.x -= 450
    }
  })
  edges.forEach(edge => {
    if (
      edge.data.type &&
      edge.data.type == 'disjunctional'
    ) {
      if (deletedNode.id == edge.source) {
        newEdge.target = edge.target
      } else if (deletedNode.id == edge.target) {
        newEdge.source = edge.source
      }
    }
  })
  if (newEdge.target && newEdge.source) {
    newEdge.id = newEdge.source + '-' + newEdge.target
  }
  addEdges([newEdge])
};

</script>

<template>
  <div>
    <div 
      class="card"
      :style="[{ minHeight: '250px', width: '350px' }, childStyle]"
    >
      <div class="card-header text-center">
        <div class="row align-items-center">
          <div class="col">
            <button 
              style="position: absolute; top: 5px; left: 5px; background: none; border: none;"
              @click="toggleVisibility" 
            >
              <i 
                class="fa" 
                :class="{ 
                  'fa-eye': data_visibility.visibility, 
                  'fa-eye-slash': !data_visibility.visibility, 
                  'strikethrough': !data_visibility.visibility 
                }"
              />
            </button>
            <h5>
              {{ data.name }}
            </h5>
            <button 
              style="position: absolute; top: 5px; right: 5px; background: none; border: none;"
              @click="deleteCondition" 
            >
              <i 
                class="fa fa-trash"
              />
            </button>
          </div>
        </div>
      </div>
      <div class="card-body">
        <div v-if="props.type == 'Restriction'">
          <RestrictionItem 
            :restriction="data" 
            :learningpath="learningpath"
          />
        </div>
        <div v-else-if="props.type == 'completion'">
          <CompletionItem 
            :completion="data"
          />
        </div>
      </div>
    </div>
    <Handle 
      id="target_and" 
      type="target" 
      :position="Position.Top" 
      :style="handleStyle"
    />
    <Handle 
      id="source_and" 
      type="source" 
      :position="Position.Bottom" 
      :style="handleStyle"
    />
    <Handle 
      id="target_or" 
      type="target" 
      :position="Position.Left" 
      :style="handleStyle"
    />
    <Handle 
      id="source_or" 
      type="source" 
      :position="Position.Right" 
      :style="handleStyle"
    />
  </div>
</template>

<style scoped>
.custom-node {
  background-color: white;
  padding: 10px;
  border: 1px solid #ccc;
}

.card-body {
  font-weight: bold;
}

.fa-eye {
  color: grey;
  opacity: 1;
}

.fa-eye-slash {
  color: grey;
  opacity: 0.5;
  text-decoration: line-through;
}

.strikethrough {
  text-decoration: line-through;
}

</style>