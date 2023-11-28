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
import { Panel, useVueFlow, isNode } from '@vue-flow/core'
import { useStore } from 'vuex';
import { useRouter } from 'vue-router';
import { watch, nextTick } from 'vue';
import { notify } from "@kyvg/vue3-notification";

// Load Store and Router
const store = useStore();
const router = useRouter();

const { nodes, toObject, setNodes, setEdges, fitView, onPaneReady, setTransform } = useVueFlow()
// Define props in the setup block
const props = defineProps(['learninggoal']); 


// Build flow-chart with edges and nodes
const flowchart = (flow) => {
  if (flow) {
      const [x = 0, y = 0] = flow.position
      setNodes(flow.nodes)
      setEdges(flow.edges)
      setTransform({ x, y, zoom: flow.zoom || 0 })
      nextTick(() => {
        fitView({ duration: 1000, padding: 0.5 })
      })
    }
};

// Emit to parent component
const emit = defineEmits();
const emitNodeCount = (count) => {
  emit('node-count-changed', count);
};

// Watch for changes in the number of nodes
watch(nodes, () => {
  emitNodeCount(nodes.value.length);
});

// Watch for changes of the learning path
watch(() => store.state.learninggoal, (newValue, oldValue) => {
  if (newValue[0].json.tree != undefined) {
    flowchart(newValue[0].json.tree)
  }else{
    setNodes([])
    setEdges([])
  }
});

// Watch for changes of the learning path
if (store.state.learninggoal[0].json.tree != undefined) {
    flowchart(store.state.learninggoal[0].json.tree)
}

// Prepare and save learning path
const onSave = () => {
    let obj = {};
    obj['tree'] = toObject();
    obj = JSON.stringify(obj);
    let result = {
        learninggoalid: props.learninggoal.id,
        name: props.learninggoal.name,
        description: props.learninggoal.description,
        json: obj,
    };
    store.dispatch('saveLearningpath', result);
    store.dispatch('fetchLearningpaths');
    store.state.learningGoalID = 0;
    store.state.editingadding = false;
    router.push({name: 'learninggoals-edit-overview'});
    window.scrollTo(0,0);

    notify({
    title: store.state.strings.title_save,
    text: store.state.strings.description_save,
    type: 'success'
  });
    
};

// Cancel learning path edition and return to overview
const onCancel = () => {
    store.state.learningGoalID = 0;
    store.state.editingadding = false;
    router.push({name: 'learninggoals-edit-overview'});
};

// Fit pane into view
onPaneReady(({ fitView,}) => {
  fitView({ padding: 0.2 })
})

// Update the position of the nodes
function updatePos() {
  let elements = toObject();
  //get all ids
  let nodeids = [];
  elements.nodes.forEach((el) => {
    if (isNode(el)) {
      nodeids.push(el.id);
    }
  })
  //get target
  let sources = [];
  elements.edges.forEach((el) => {
    if (el.source) {
      sources.push(el.source);
    }
  })
  sources = sources.filter(onlyUnique);
  let targets = nodeids.filter(x => !sources.includes(x));

  //set all target to one y
  if(targets.length > 1){
    let targetEndY = null;
    targets.forEach((taregt) => {
      //get target
      let target_node = elements.nodes.filter(search_target => {
        return search_target.id === taregt
      })
      if(targetEndY){
        elements.nodes = elements.nodes.map(element_node => {
          if (element_node.id === taregt) {
            let position = {
              x: targetEndY,
              y: element_node.position.y,
            }
            return { ...element_node, position: position };
          }
          return element_node;
        });
      }else{
        targetEndY = target_node[0].position.x;
      }
    });

  }

  while (true) {
    let new_targets = [];
    //get target nodes position and targets node sources
    targets.forEach((taregt) => {
      //get target
      let target_node = elements.nodes.filter(search_target => {
        return search_target.id === taregt
      })
      //get sources of target
      let source_nodes = elements.edges.filter(search_sources => {
        return search_sources.target === target_node[0].id;
      })

      //update position, construct new targets 
      source_nodes.forEach((source_node) => {
        elements.nodes = elements.nodes.map(element_node => {
            if (element_node.id === source_node.source) {
              let position = {
                x: target_node[0].position.x - 400,
                y: element_node.position.y,
              }
              new_targets.push(element_node.id);
              return { ...element_node, position: position };
            }
            return element_node;
        });
      });
    })
    targets = new_targets;
    if (new_targets.length === 0) {
      break;
    }
  }
  flowchart(elements)
}

// Get all nodes id
function onlyUnique(value, index, array) {
  return array.indexOf(value) === index;
}

// Toggle the dark mode of the flow-chart
function toggleClass() {
  emit('change-class');
}

</script>

<template>
  <Panel class="save-restore-controls">
    <button class="btn btn-primary m-2" @click="onSave">{{store.state.strings.save}}</button>
    <button class="btn btn-secondary m-2" @click="onCancel">{{store.state.strings.btncancel}}</button>
    <button class="btn btn-info m-2" @click="updatePos">{{store.state.strings.btnupdate_positions}}</button>
    <button class="btn btn-warning m-2" @click="toggleClass">{{store.state.strings.btntoggle}}</button>
    <a href="/course/edit.php?category=0" target="_blank" rel="noreferrer noopener">
      <button class="btn btn-link" :title="store.state.strings.btncreatecourse">{{ store.state.strings.btncreatecourse }}</button>
    </a>
  </Panel>
</template>
