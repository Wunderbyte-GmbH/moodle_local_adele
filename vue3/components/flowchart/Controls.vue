<script setup>
import { Panel, useVueFlow } from '@vue-flow/core'
import { useStore } from 'vuex';
import { useRouter } from 'vue-router';
import { watch, onUnmounted } from 'vue';
import { notify } from "@kyvg/vue3-notification";


const store = useStore();
const router = useRouter();

const { nodes, toObject, setNodes, setEdges, setTransform } = useVueFlow()

const props = defineProps(['learninggoal']); // Define props in the setup block

const flowchart = (flow) => {
  if (flow) {
      const [x = 0, y = 0] = flow.position
      setNodes(flow.nodes)
      setEdges(flow.edges)
      setTransform({ x, y, zoom: flow.zoom || 0 })
    }
};

let stopNodeWatcher;
const emit = defineEmits();
const emitNodeCount = (count) => {
  emit('node-count-changed', count);
};

// Watch for changes in the number of nodes
watch(nodes, () => {
  emitNodeCount(nodes.value.length);
});

watch(() => store.state.learninggoal, (newValue, oldValue) => {
  if (newValue[0].json.tree != undefined) {
    flowchart(newValue[0].json.tree)
  }else{
    setNodes([
      {
        id: '1',
        type: 'custom',
        label: 'input node',
        position: { x: 250, y: 25 },
      },
    ])
    setEdges([])
  }
});

if (store.state.learninggoal[0].json.tree != undefined) {
    flowchart(store.state.learninggoal[0].json.tree)
}


const onSave = () => {
    let action = props.learninggoal.id == 0 ? 'saved' : 'edited';

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
    title: "Learning Path " + action,
    text: "You have " + action + " the Learning Path!",
    type: 'success'
  });
    
};

const onCancel = () => {
    store.state.learningGoalID = 0;
    store.state.editingadding = false;
    router.push({name: 'learninggoals-edit-overview'});
};


</script>

<template>
  <Panel position="top-right" class="save-restore-controls">
    <button style="background-color: #33a6b8" @click="onSave">save</button>
    <button style="background-color: #6a7071" @click="onCancel">cancel</button>
  </Panel>
</template>
