<style scoped>
    .learninggoals-edit-list {
        padding-top: 1rem;
    }
    .learninggoals-edit-add {
        padding-top: 20px;
    }
    .learninggoals-edit-add-form > div > p > input {
        margin-bottom: 5px;
        font-size: 1rem;
    }
    input.thinking_skill[type="text"] {
        border: 1.5px solid #009;
        border-bottom: 2.5px solid #009;
    }
    input.thinking_skill[type="text"]:focus {
        outline: none;
        box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.075);
        --webkit-box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.075);
        --moz-box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.075);
        border-color: #009;
        transition: border linear .2s, box-shadow linear .2s;
    }
    input.content[type="text"] {
        border: 1.5px solid #600;
        border-bottom: 2.5px solid #600;
    }
    input.content[type="text"]:focus {
        outline: none;
        border: 1.5px solid #600;
        border-bottom: 2.5px solid #600;
    }
    input.resource[type="text"] {
        border: 1.5px solid #090;
        border-bottom: 2.5px solid #090;
    }
    input.resource[type="text"]:focus {
        outline: none;
        border: 1.5px solid #090;
        border-bottom: 2.5px solid #090;
    }
    input.product[type="text"] {
        border: 1.5px solid #909;
        border-bottom: 2.5px solid #909;
    }
    input.product[type="text"]:focus {
        outline: none;
        border: 1.5px solid #909;
        border-bottom: 2.5px solid #909;
    }
    input.group[type="text"] {
        border: 1.5px solid #990;
        border-bottom: 2.5px solid #990;
    }
    input.group[type="text"]:focus {
        outline: none;
        border: 1.5px solid #990;
        border-bottom: 2.5px solid #990;
    }
    input[type="text"] {
        transition: border-color 250ms ease;
        appearance: none;
        border-radius: 4px;
        border: 1.5px solid #e9ebeb;
        border-bottom: 2.5px solid #e9ebeb;
        padding: 0.15em 0.3em;
    }
    input[type="text"]:focus {
        outline: none;
        border-color: #999;
    }
    input[type="text"]::-webkit-input-placeholder {
        /* Chrome/Opera/Safari */
        color: rgba(19, 40, 48, 0.54);
    }
    .fa-clipboard {
        cursor: pointer;
        margin-right: 0px;
    }
    @import 'https://cdn.jsdelivr.net/npm/@vue-flow/core@1.26.0/dist/style.css';
    @import 'https://cdn.jsdelivr.net/npm/@vue-flow/core@1.26.0/dist/theme-default.css';
    @import 'https://cdn.jsdelivr.net/npm/@vue-flow/controls@latest/dist/style.css';
    @import 'https://cdn.jsdelivr.net/npm/@vue-flow/minimap@latest/dist/style.css';
    @import 'https://cdn.jsdelivr.net/npm/@vue-flow/node-resizer@latest/dist/style.css';

.dndflow{flex-direction:column;display:flex;height:500px}.dndflow aside{color:#fff;font-weight:700;border-right:1px solid #eee;padding:15px 10px;font-size:12px;background:rgba(16,185,129,.75);-webkit-box-shadow:0px 5px 10px 0px rgba(0,0,0,.3);box-shadow:0 5px 10px #0000004d}.dndflow aside .nodes>*{margin-bottom:10px;cursor:grab;font-weight:500;-webkit-box-shadow:5px 5px 10px 2px rgba(0,0,0,.25);box-shadow:5px 5px 10px 2px #00000040}.dndflow aside .description{margin-bottom:10px}.dndflow .vue-flow-wrapper{flex-grow:1;height:100%}@media screen and (min-width: 640px){.dndflow{flex-direction:row}.dndflow aside{min-width:25%}}@media screen and (max-width: 639px){.dndflow aside .nodes{display:flex;flex-direction:row;gap:5px}}
.basicflow.dark{background:#57534e;}
</style>

<template>
    <div class="learninggoals-edit">
      <notifications width="100%" />

        <div v-if="$store.state.editingadding == false">
            <h3>{{store.state.strings.pluginname}}</h3>
            <div class="learninggoals-edit-add">
                <router-link :to="{ name: 'learninggoal-new' }" tag="button" class="btn btn-primary">{{store.state.strings.learninggoal_form_title_add}}</router-link>
            </div>
            <h2>{{store.state.strings.overviewlearningpaths}}</h2>
            <div class="description">{{store.state.strings.learninggoals_edit_site_description}}</div>
                <span v-if="store.state.learningpaths == ''">
                    {{store.state.strings.learninggoals_edit_site_no_learningpaths}}
                </span>
                <span v-else>
                  <div v-for="singlelearninggoal in store.state.learningpaths" style="margin-bottom: 10px">
                      <div class="learninggoal-top-level" v-if="singlelearninggoal.name !== 'not found'">
                          <div>
                            <div class="card" style="width: 18rem;">
                              <div class="card-body">
                                <h5 class="card-title">{{ singlelearninggoal.name }}</h5>
                                <p class="card-text">{{ singlelearninggoal.description }}</p>
                                <router-link :to="{ name: 'learninggoal-edit', params: { learninggoalId: singlelearninggoal.id }}" :title="store.state.strings.edit">
                                  <i class="icon fa fa-pencil fa-fw iconsmall m-r-0" :title="store.state.strings.edit"></i>
                                </router-link>
                                <a href="" v-on:click.prevent="duplicateLearningpath(singlelearninggoal.id)" :title="store.state.strings.duplicate">
                                    <i class="icon fa fa-copy fa-fw iconsmall m-r-0" :title="store.state.strings.duplicate"></i>
                                </a>
                                <a href="" v-on:click.prevent="showDeleteConfirm(singlelearninggoal.id)" :title="store.state.strings.delete">
                                    <i class="icon fa fa-trash fa-fw iconsmall" :title="store.state.strings.delete"></i>
                                </a>
                                </div>
                            </div>
                          </div>
                          <div class="alert-danger p-3 m-t-1 m-b-1" v-show="clicked[singlelearninggoal.id]">
                              <div>{{store.state.strings.deletepromptpre}}{{singlelearninggoal.name}}{{store.state.strings.deletepromptpost}}</div>
                              <div class="m-t-1">
                                  <button class="btn btn-danger m-r-0" @click="deleteLearningpathConfirm(singlelearninggoal.id)" :title="store.state.strings.btnconfirmdelete">
                                  {{ store.state.strings.btnconfirmdelete }}</button>
                                  <button type=button @click="cancelDeleteConfirm(singlelearninggoal.id)" class="btn btn-secondary">{{store.state.strings.cancel}}</button>
                              </div>
                          </div>
                      </div>
                    </div>
                </span>
        </div>
        <div v-if="$store.state.editingadding == true">
      <h3>{{ store.state.strings.learninggoal_form_title_edit }}</h3>
      <div class="learninggoals-edit-add-form">
        <div v-for="goal in store.state.learninggoal">
          <p>
            <h4>{{ store.state.strings.fromlearningtitel }}</h4>
            <input
              v-if="$store.state.learningGoalID == 0"
              :placeholder="store.state.strings.goalnameplaceholder"
              autofocus
              type="text"
              v-autowidth="{ maxWidth: '960px', minWidth: '20px', comfortZone: 0 }"
              v-model="goalname"
            />
            <input
              v-else
              type="text"
              v-autowidth="{ maxWidth: '960px', minWidth: '20px', comfortZone: 0 }"
              v-model="goal.name"
            />
          </p>
          <p>
            <h4>{{ store.state.strings.fromlearningdescription }}</h4>
            <input
              v-if="$store.state.learningGoalID == 0"
              :placeholder="store.state.strings.goalsubjectplaceholder"
              type="textarea"
              v-autowidth="{ maxWidth: '960px', minWidth: '40%', comfortZone: 0 }"
              v-model="goaldescription"
            />
            <input
              v-else
              type="textarea"
              v-autowidth="{ maxWidth: '960px', minWidth: '40%', comfortZone: 0 }"
              v-model="goal.description"
            />
          </p>
          <div class="dndflow" @drop="onDrop">
            <VueFlow @dragover="onDragOver" :default-viewport="{ zoom: 1.0 }" :class="{ dark }" class="basicflow">
              <Background :pattern-color="dark ? '#FFFFFB' : '#aaa'" gap="8" />
              <template #connection-line="{ sourceX, sourceY, targetX, targetY, sourcePosition, targetPosition }">
                <ConnectionLine
                  :source-x="sourceX"
                  :source-y="sourceY"
                  :target-x="targetX"
                  :target-y="targetY"
                  :source-position="sourcePosition"
                  :target-position="targetPosition"
                />
              </template>
              <template #node-custom="{ data }">
                <CustomrNode :data="data" />
              </template>
            </VueFlow>
            <Controls :learninggoal="store.state.learninggoal[0]" 
              @node-count-changed="updateNumberOfNodesInChild"
              @change-class="toggleClass"
            />
            <Sidebar :courses="store.state.availablecourses" :strings="store.state.strings" />
          </div>
          <p>
            <a href="/course/edit.php?category=0" target="_blank" rel="noreferrer noopener">
              <button class="btn btn-secondary" :title="store.state.strings.btncreatecourse">{{ store.state.strings.btncreatecourse }}</button>
            </a>
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch, nextTick } from 'vue';
import { onBeforeRouteUpdate } from 'vue-router';
import { VueFlow, useVueFlow } from '@vue-flow/core';
import { useStore } from 'vuex';
import Sidebar from './flowchart/Sidebar.vue';
import Controls from './flowchart/Controls.vue';
import ConnectionLine from './flowchart/ConnectionLine.vue'
import CustomrNode from './flowchart/CustomNode.vue'
import { useRouter } from 'vue-router';
import { notify } from "@kyvg/vue3-notification";
import { Background } from '@vue-flow/background'

const store = useStore()
const router = useRouter()

const goalname = ref('')
const goaldescription = ref('')
const clicked = ref({})

const dark = ref(false)
function toggleClass() {
  console.log(!dark.value);
  return (dark.value = !dark.value)
}

let id = ref(0);
// Update the variable when the custom event is emitted
const updateNumberOfNodesInChild = (count) => {
  id.value = count;
};

function getId() {
  id.value = id.value+1;
  return `dndnode_${id.value}`
}

const { nodes, findNode, onConnect, addEdges, addNodes, project, vueFlowRef } = useVueFlow({
  nodes: [
    {
      id: '1',
      type: 'custom',
      label: 'input node',
      data: { color: '#A8D8B9' },
      position: { x: 250, y: 25 },
    },
  ],
})
// Watch for changes in the number of nodes
const numberOfNodes = ref(nodes.length);
watch(nodes, () => {
  numberOfNodes.value = nodes.length;
});

function onDragOver(event) {
  event.preventDefault()

  if (event.dataTransfer) {
    event.dataTransfer.dropEffect = 'move'
  }
}

onConnect((params) => addEdges(params))

function onDrop(event) {
  const type = event.dataTransfer?.getData('application/vueflow')
  const data = JSON.parse(event.dataTransfer?.getData('application/data'));
  const { left, top } = vueFlowRef.value.getBoundingClientRect()

  const position = project({
    x: event.clientX - left,
    y: event.clientY - top,
  })

  const newNode = {
    id: getId(),
    type,
    position,
    label: `${type} node`,
    data: data
  }
  addNodes([newNode])

  // align node position after drop, so it's centered to the mouse
  nextTick(() => {
    const node = findNode(newNode.id)
    const stop = watch(
      () => node.dimensions,
      (dimensions) => {
        if (dimensions.width > 0 && dimensions.height > 0) {
          node.position = { x: node.position.x - node.dimensions.width / 2, y: node.position.y - node.dimensions.height / 2 }
          stop()
        }
      },
      { deep: true, flush: 'post' },
    )
  })
}

const checkRoute = (currentRoute) => {
    if(currentRoute == undefined){
        router.push({ name: 'learninggoals-edit-overview' });
    }
  else if (currentRoute.name === 'learninggoal-edit') {

    store.state.editingadding = true;
    nextTick(() => showForm(currentRoute.params.learninggoalId));
  } else if (currentRoute.name === 'learninggoal-new') {
    store.state.editingadding = true;
    nextTick(() => showForm(null));
  }
};

onMounted(() => {
  store.dispatch('fetchLearningpaths');
  store.dispatch('fetchAvailablecourses');
  checkRoute(router.value);
});

const showDeleteConfirm = (index) => {
  // Dismiss other open confirm delete prompts.
  clicked.value = {};
  // Show the confirm delete prompt.
  clicked.value[index] = true;
};

const cancelDeleteConfirm = (index) => {
  if (clicked.value.hasOwnProperty(index)) clicked.value[index] = !clicked.value[index];
};

const deleteLearningpathConfirm = (learninggoalid) => {
  const result = {
    learninggoalid: learninggoalid,
  };
  store.dispatch('deleteLearningpath', result);
  clicked.value = {};
  notify({
    title: store.state.strings.title_delete,
    text: store.state.strings.description_delete,
    type: 'warn'
  });
};

const duplicateLearningpath = (learninggoalid) => {
  const result = {
    learninggoalid: learninggoalid,
  };
  store.dispatch('duplicateLearningpath', result);
  notify({
    title: store.state.strings.title_duplicate,
    text: store.state.strings.description_duplicate,
    type: 'success'
  });
};

const showForm = async (learninggoalId = null) => {
  goalname.value = ''
  goaldescription.value = ''
  if (learninggoalId) {
    store.state.learningGoalID = learninggoalId;
    store.dispatch('fetchLearningpath')
    store.state.editingadding = true
    // Do something here in case of an edit.
  } else {
    store.dispatch('fetchLearningpath')
    store.state.editingadding = true
    // Do something here in case of an add.
  }
  window.scrollTo(0, 0)
  // This has to happen after the save button is hit.
};

watch(goalname, (newGoalName) => {
  store.state.learninggoal[0].name = newGoalName;
});

watch(goaldescription, (newGoalDescription) => {
  store.state.learninggoal[0].description = newGoalDescription;
});

onBeforeRouteUpdate((to, from, next) => {
  checkRoute(to);
  next();
});
</script>