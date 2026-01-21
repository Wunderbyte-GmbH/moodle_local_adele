<template>
  <div>
    <HelpingSlider />
    <h3>{{ store.state.strings.pluginname }}</h3>
    <div class="col-lg-2">
      <button
        v-if="store.state.view != null && (store.state.view == 'manager' || store.state.view == 'assistant')"
        type="button"
        class="btn btn-primary mt-4 mb-4 btn-block"
        @click.prevent="addNewLearningpath()"
      >
        {{ store.state.strings.learningpath_form_title_add }}
      </button>
      <button
        type="button"
        class="btn btn-secondary  mt-4 mb-4 btn-block"
        data-toggle="modal"
        data-target="#helpingSlider"
      >
        {{ store.state.strings.main_intro_slider }}
        <i
          :class="store.state.version ? 'fa-solid fa-book-open-reader' : 'fas fa-book'"
        />
      </button>
    </div>
    <h2>
      {{ store.state.strings.overviewlearningpaths }}
    </h2>
    <input
      v-model="search"
      class="form-control search"
      :placeholder="store.state.strings.placeholder_lp_search"
    >
    <span v-if="store.state.learningpaths == ''">
      {{ store.state.strings.learningpaths_edit_site_no_learningpaths }}
    </span>
    <span
      v-else
      class="learningcardcont">
      <div
        v-for="singlelearningpath in filteredLpItem"
        :key="singlelearningpath.id"
        class="learningcard"
      >
        <div
          v-if="
            store.state.editablepaths[singlelearningpath.id] != undefined ||
            store.state.view == 'manager' || store.state.view == 'assistant' || 
            (
              singlelearningpath.visibility == 1 &&
              store.state.view != null
            )
          "
          class="wrap"
        >
          <div
            class="card shadow"
            style="width: 450px;"
          >
          <div class="card-header bg-primary text-white">
            <div class="position-relative">
              <h5 class="text-center mb-0">
                {{ singlelearningpath.name }}
              </h5>
              <a
                v-if="
                  store.state.editablepaths[singlelearningpath.id] != undefined ||
                  store.state.view == 'manager'
                "
                class="icon-link position-absolute"
                href=""
                v-tooltip="singlelearningpath.visibility == 1 ? 'Make Invisible' : 'Make Visible'"
                @click.prevent="toggleVisibility(singlelearningpath)"
              >
                <i
                  class="icon fa-fw iconsmall"
                  :class="singlelearningpath.visibility== '1' ? 'fas fa-eye' : 'fas fa-eye-slash'"
                />
              </a>
            </div>
          </div>
            <div
              class="card-body" 
            >
              <div
                class="mb-2"
                :style="{
                  height: '10rem',
                  backgroundImage: singlelearningpath.image ? `url(${singlelearningpath.image})` : '',
                  backgroundSize: 'cover',
                  backgroundPosition: 'center',
                  backgroundColor: '#cccccc',
                  borderRadius: '1rem'
                }"
              >
                <div class="overlay">
                  <a
                    v-if="
                      store.state.view == 'manager'||store.state.view == 'assistant'
                    "
                    class="icon-link"
                    href=""
                    v-tooltip="store.state.strings.duplicate"
                    @click.prevent="duplicateLearningpath(singlelearningpath.id)"
                  >
                    <i
                      class="icon m-r-0 fas fa-copy fa-fw iconsmall"
                    />
                  </a>
                  <a
                    v-if="
                      store.state.editablepaths[singlelearningpath.id] != undefined ||
                      store.state.view == 'manager'
                    "
                    class="icon-link"
                    href=""
                    v-tooltip="store.state.strings.edit"
                    @click.prevent="editLearningpath(singlelearningpath.id)"
                  >
                    <i
                      class="icon m-r-0 fas fa-pencil fa-fw iconsmall"
                    />
                  </a>
                  <a
                    v-if="
                       (store.state.view == 'manager') || (store.state.view == 'assistant' && singlelearningpath.isowner == 'true')
                    "
                    class="icon-link"
                    href=""
                    v-tooltip="store.state.strings.delete"
                    @click.prevent="showDeleteConfirm(singlelearningpath.id)"
                  >
                    <i
                      class="icon fa-fw iconsmall fa"
                      :class="store.state.version ? 'fa-trash' : 'fa-trash-o'"
                    />
                  </a>
                </div>
              </div>
              <div>
                <b>
                  {{ store.state.strings.main_description }}
                </b>
                {{ singlelearningpath.description }}
              </div>
            </div>
          </div>
          <div
            v-show="clicked[singlelearningpath.id]"
            class="alert-danger p-3 m-t-1 m-b-1 rounded deletealert"
            style="max-width: 100%;"
          >
            <div>{{ store.state.strings.deletepromptpre }}{{ singlelearningpath.name }}{{ store.state.strings.deletepromptpost }}</div>

            <div class="mt-4 d-flex justify-content-between">
              <button
                class="btn btn-danger mr-2"
                :title="store.state.strings.btnconfirmdelete"
                @click="deleteLearningpathConfirm(singlelearningpath.id)"
              >
                {{ store.state.strings.btnconfirmdelete }}</button>
              <button
                type="button"
                class="btn btn-secondary"
                @click="cancelDeleteConfirm(singlelearningpath.id)"
              >
                {{ store.state.strings.cancel }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </span>
    <div v-if="viewLearningPaths && viewLearningPaths.length != 0">
      <h2>
        Viewable learningpath
      </h2>
      <span class="learningcardcont">
        <div
          v-for="viewablelearningpath in viewLearningPaths"
          :key="viewablelearningpath.id"
          class="learningcard"
        >
          <div
            v-if="viewablelearningpath.visibility == 1"
            class="card shadow"
            style="width: 450px;"
          >
            <div class="card-header text-center bg-primary text-white">
              <h5>
                {{ viewablelearningpath.name }}
              </h5>
            </div>
            <div
              class="card-body"
            >
              <div
                class="mb-2"
                :style="{
                  height: '10rem',
                  backgroundImage: viewablelearningpath.image ? `url(${viewablelearningpath.image})` : '',
                  backgroundSize: 'cover',
                  backgroundPosition: 'center',
                  backgroundColor: '#cccccc',
                  borderRadius: '1rem'
                }"
              >
                <div class="overlay">
                  <a
                    class="icon-link"
                    href=""
                    v-tooltip="store.state.strings.view"
                    @click.prevent="viewLearningpath(viewablelearningpath.id)"
                  >
                    <i
                      class="icon m-r-0 fas fa-solid fa-play fa-fw iconsmall"
                    />
                  </a>
                </div>
              </div>
              <div>
                <b>
                  {{ store.state.strings.main_description }}
                </b>
                {{ viewablelearningpath.description }}
              </div>
            </div>
          </div>

        </div>
      </span>
    </div>
  </div>
</template>

<script setup>
// Import needed libraries
import { computed, ref, watch, onMounted, } from 'vue'
import { useStore } from 'vuex'
import { useRouter } from 'vue-router';
import { notify } from "@kyvg/vue3-notification"
import HelpingSlider from '../components/modals/HelpingSlider.vue'

const filteredLpItem = computed(() => {
  if (search.value != '' && ' ') {
    filteredLp = store.state.learningpaths.filter((lp) => lp.name.toLowerCase().includes(search.value.toLowerCase()));
    } else {
    filteredLp = store.state.learningpaths;
    }
    return filteredLp;
})

const viewLearningPaths = computed(() => {
    return store.state.viewlearningpaths;
})

const search = ref('');
let learningPaths = [];
let filteredLp = [];

onMounted(async () => {
  store.state.undoNodes = []
  await store.dispatch('fetchImagePaths')
  watch(() => store.state.learningpaths, async () => {
    if (store.state.learningpaths) {
    learningPaths = store.state.learningpaths;
    filteredLp = [...learningPaths];
    filteredLp.forEach(lp => {
          if (lp.visibility === undefined) {
            lp.visibility = false;
          }
        });
    }
  }, {
       deep: true
      });
});

// Handle toggling of visibility
const toggleVisibility = (learningPath) => {
  if (learningPath.visibility == 0) {
    learningPath.visibility = 1
  } else {
    learningPath.visibility = 0
  }
  store.dispatch('updateLearningPathVisibility',
    {
      lpid: learningPath.id,
      visibility: learningPath.visibility
    }
  )
  notify({
    title: store.state.strings.title_change_visibility,
    text: store.state.strings.description_change_visibility,
    type: 'success'
  });
};


// Load Store and Router
const store = useStore()
const router = useRouter()

// Define constants that will be referenced
const clicked = ref({})

// Delete confirmation before learning path will be deleted
const showDeleteConfirm = (index) => {
  clicked.value = {};
  clicked.value[index] = true;
};

// Edit learning path deletion
const viewLearningpath = async (singlelearningpathid) => {
  store.state.learningPathID = singlelearningpathid
  await store.dispatch('fetchLearningpath')
  router.push({
    name: 'learningpath-view',
    params: { learningpathId: singlelearningpathid  }
  })
};

// Edit learning path deletion
const editLearningpath = async (singlelearningpathid) => {
  store.state.learningPathID = singlelearningpathid
  await store.dispatch('fetchLearningpath')
  router.push({
    name: 'learningpath-edit',
    params: { learningpathId: singlelearningpathid  }
  })
};

// Edit learning path deletion
const addNewLearningpath = async () => {
  store.state.learningPathID = 0
  await store.dispatch('fetchLearningpath')
  router.push({
    name: 'learningpath-new',
    params: { learningpathId: 0  }
  })
};

// Cancel learning path deletion
const cancelDeleteConfirm = (index) => {
  clicked.value[index] = false
};

// Deleting learning path
const deleteLearningpathConfirm = (learningpathid) => {
  const result = {
    learningpathid: learningpathid,
  };
  store.dispatch('deleteLearningpath', result);
  clicked.value = {};
  notify({
    title: store.state.strings.title_delete,
    text: store.state.strings.description_delete,
    type: 'warn'
  });
};

// Duplicate learning path
const duplicateLearningpath = (learningpathid) => {
  const result = {
    learningpathid: learningpathid,
  };
  store.dispatch('duplicateLearningpath', result);
  notify({
    title: store.state.strings.title_duplicate,
    text: store.state.strings.description_duplicate,
    type: 'success'
  });
};

</script>

<style scoped>
  .position-relative {
    position: relative;
  }

  .icon-link.position-absolute {
    position: absolute;
    right: 0; /* Aligns the icon to the right */
    top: 50%; /* Vertically centers the icon */
    transform: translateY(-50%); /* Adjust for perfect vertical centering */
    padding-right: 15px; /* Adjust as needed to give space from the right edge */
  }
  .search {
    max-width: 500px;
  }
  .wrap {
    display: flex;
    flex-direction: column;
    height: 100%;
    position: relative;
  }
  .learningcardcont {
    display: flex;
    flex-direction: row;
    flex-wrap: wrap;
  }

  .learningcard {
    display: flex;
    flex-flow: column;
  }

  .card-header {
    min-height: 56px;
  }

  .deletealert {
    display: block;
    max-width: 100%;
    position: absolute;
    left: 8%;
    top: 30%;
    margin: 0 auto;
  }
  .overlay {
    position: relative;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background-color: rgba(0, 0, 0, 0.4); /* Semi-transparent gray */
    display: flex;
    justify-content: center;
    align-items: center;
    width: 70%; /* Adjust width as needed */
    height: 50%; /* Adjust height as needed */
    border-radius: 15px; /* Rounded edges */
  }

  .card {
    height: 100%;
    display: flex;
    margin: 20px;
  }

  .icon-link {
    color: white;
    cursor: pointer;
    padding: 10px;
    margin: 0 15px;
    display: inline-flex;
  }

  .fa-copy,
  .fa-pencil,
  .fa-trash,
  .fa-trash-o {
    font-size: 20px;
  }

  .icon-link:hover {
    color: lightgray; /* Hover effect */
  }


  @media (max-width: 767px) {
    .card {
      width: 100% !important;
    }

    .learningcardcont {
      width: 100%;
      justify-content: center;
    }

    .learningcard {
      width: 100%;
    }
   }

</style>
