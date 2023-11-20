<script setup>
import { Panel, useVueFlow } from '@vue-flow/core'
import { useStore } from 'vuex';
import { useRouter } from 'vue-router';

const store = useStore();
const router = useRouter();

const { toObject, setNodes, setEdges, setTransform } = useVueFlow()

const props = defineProps(['learninggoal']); // Define props in the setup block

if (props.learninggoal.json.tree != undefined) {
  console.log('props.learninggoal');
  const flow = props.learninggoal.json.tree;
  if (flow) {
    const [x = 0, y = 0] = flow.position
    setNodes(flow.nodes)
    setEdges(flow.edges)
    setTransform({ x, y, zoom: flow.zoom || 0 })
  }
}


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
