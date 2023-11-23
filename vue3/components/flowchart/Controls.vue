<script setup>
import { Panel, useVueFlow, isNode } from '@vue-flow/core'
import { useStore } from 'vuex';
import { useRouter } from 'vue-router';
import { watch } from 'vue';
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

const onCancel = () => {
    store.state.learningGoalID = 0;
    store.state.editingadding = false;
    router.push({name: 'learninggoals-edit-overview'});
};

function updatePos() {
  let elements = toObject();
  elements.nodes.forEach((el) => {
    if (isNode(el)) {
      el.position = {
        x: Math.ceil(el.position.x / 10) * 10,
        y: Math.ceil(el.position.y / 10) * 10,
      }
    }
  })
  flowchart(elements)
}

function toggleClass() {
  emit('change-class');
}

</script>

<template>
  <Panel position="bottom-center" class="save-restore-controls">
    <button class="btn btn-primary" @click="onSave">{{store.state.strings.save}}</button>
    <button class="btn btn-secondary" @click="onCancel">{{store.state.strings.btncancel}}</button>
    <button class="btn btn-info" @click="updatePos">{{store.state.strings.btnupdate_positions}}</button>
    <button class="btn btn-warning" @click="toggleClass">{{store.state.strings.btntoggle}}</button>
  </Panel>
</template>
