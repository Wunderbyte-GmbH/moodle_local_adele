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
 * text Inputs component.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */ -->

<script setup>
import { ref, watch, onMounted } from 'vue';
import absUrl from '../../composables/absUrl';
import { notify } from "@kyvg/vue3-notification";
import { useStore } from 'vuex'
import userSearch from './userSearch.vue';

const props = defineProps({
  goal: {
      type: Object,
      default: null,
    },
});

// Load Store and Router
const store = useStore()

const emit = defineEmits([
    'change-GoalName',
    'change-GoalDescription',
    'change-LpImage',
]);

// Define constants that will be referenced
const goalname = ref('')
const goaldescription = ref('')
const showCourseImageSelection = ref(false)
const selectionImages = ref([])
const selectedCourseImagePath = ref('')

const newImageFile = ref(null);
const newImagePreview = ref(null);

const selectCourseImage = (path) => {
  selectedCourseImagePath.value = path;
  showCourseImageSelection.value = false;
  emit('change-LpImage', selectedCourseImagePath.value);
};

const onFileChange = (event) => {
  const file = event.target.files[0];
  newImageFile.value = file;
  if (file) {
    const reader = new FileReader();
    reader.onload = (e) => {
      newImagePreview.value = e.target.result;
    };
    reader.readAsDataURL(file);
  } else {
    newImagePreview.value = null;
  }

};

const uploadNewImage = async () => {
  if (!newImageFile.value) return;
  const reader = new FileReader();
  reader.onload = async (e) => {
    const base64File = e.target.result.split(',')[1]; // Get the Base64 part of the file
    try {
      const response = await store.dispatch('uploadNewLpImage', base64File);
      notify({
        title: store.state.strings.image_title_save,
        text: store.state.strings.image_description_save,
        type: 'success'
      })
      selectCourseImage(response.filename)
      newImagePreview.value = null;
    } catch (error) {
      console.error('Error uploading image:', error);
    }
  };
  reader.readAsDataURL(newImageFile.value);
};

onMounted(() => {
  goalname.value = props.goal.name
  goaldescription.value = props.goal.description
  selectedCourseImagePath.value = props.goal.image
  if (store.state.lpimages.node_background_image) {
    store.state.lpimages.node_background_image.forEach((lpimage) => {
      if (
        lpimage.path.includes('/local/adele') ||
        lpimage.path.includes('/uploaded_file_lp_' + store.state.learningPathID + '.')
      ) {
        selectionImages.value.push(lpimage.path)
      }
    })
  }
})

// Watch changes on goalname
watch(goalname, (newGoalName) => {
    store.state.learningpath.name = newGoalName;
    emit('change-GoalName', newGoalName);
});

// Watch changes on goaldescription
watch(goaldescription, (newGoalDescription) => {
    store.state.learningpath.description = newGoalDescription;
    emit('change-GoalDescription', newGoalDescription);
});

// Watch changes on goaldescription
watch(() => store.state.learningpath, async () => {
  goalname.value = store.state.learningpath.name
  goaldescription.value = store.state.learningpath.description
  selectedCourseImagePath.value = store.state.learningpath.image
}, { deep: true } );

// Edit learning path deletion
const editLearningpath = async (singlelearningpathid) => {
  store.state.learningPathID = singlelearningpathid
  window.open( store.state.wwwroot + '/local/adele/index.php#/learningpaths/edit/' + singlelearningpathid, '_blank');
};

</script>

<template>
  <div>
    <div v-if="store.state.view!='teacher'">
      <div class="col-12">
        <label
          class="font-weight-bold h4"
          for="goalnameplaceholder"
        >
          {{ store.state.strings.fromlearningtitel }}
          <span
            class="required-sign"
            style="display: inline-flex; align-items: center; line-height: 1; width: auto;"
          >
            <i
              v-tooltip="store.state.strings.required"
              class="fas fa-exclamation-circle text-danger ml-2"
              :aria-label="store.state.strings.required"
              role="img"
            />
          </span>
        </label>
        <div class="mb-2">
          <input
            id="goalnameplaceholder"
            data-testid="learningpath-title"
            v-model="goalname"
            v-autowidth="{ maxWidth: '960px', minWidth: '20px', comfortZone: 0 }"
            class="form-control fancy-input"
            :placeholder="store.state.strings.goalnameplaceholder"
            type="text"
            autofocus
          >
        </div>
      </div>
      <div class="col-12">
        <label
          class="font-weight-bold h4"
          for="goalsubjectplaceholder"
        >
          {{ store.state.strings.fromlearningdescription }}
          <span
            class="required-sign"
            style="display: inline-flex; align-items: center; line-height: 1; width: auto;"
          >
            <i
              v-tooltip="store.state.strings.required"
              class="fas fa-exclamation-circle text-danger ml-2"
              :aria-label="store.state.strings.required"
              role="img"
            />
          </span>
        </label>
        <div class="mb-2">
          <textarea
            id="goalsubjectplaceholder"
            data-testid="learningpath-description"
            v-model="goaldescription"
            :name="store.state.strings.goalsubjectplaceholder"
            v-autowidth="{ maxWidth: '960px', minWidth: '40%', comfortZone: 0 }"
            class="form-control fancy-input"
            :placeholder="store.state.strings.goalsubjectplaceholder"
          />
        </div>
      </div>
      <div class="col-12 row">
        <div class="col-6">
          <h4 class="font-weight-bold">
            {{ store.state.strings.from_default_node_image }}
          </h4>
          <div class="mb-2">
            {{ store.state.strings.uploaddefaultimage }}
            <div
              v-if="store.state.lpimages && Object.keys(store.state.lpimages).length > 0"
              class="mb-2"
            >
              <button
                type="button"
                class="btn btn-info"
                @click="showCourseImageSelection = !showCourseImageSelection"
              >
                {{ store.state.strings.selectdefaultimage }}
              </button>
              <div
                v-if="selectedCourseImagePath"
                class="image-preview-container"
              >
                <img
                  :src="absUrl(selectedCourseImagePath, store.state.wwwroot)"
                  alt="Selected Image"
                  class="image-preview"
                >
                <button
                  class="deselect-btn"
                  @click="selectCourseImage()"
                >
                  {{ store.state.strings.deselectdefaultimage }}
                </button>
              </div>
              <div
                v-if="showCourseImageSelection"
                class="image-selection-container"
              >
                <div
                  v-for="path in selectionImages"
                  :key="path"
                  class="image-option"
                  @click="selectCourseImage(path)"
                >
                  <img
                    :src="path"
                    alt="Image"
                    class="image-option-img"
                  >
                </div>
              </div>
            </div>
            <div>
              <label for="newImage">
                {{ store.state.strings.uploadowndefaultimage }}
              </label>
              <input type="file" id="newImage" @change="onFileChange">
              <div
                v-if="newImagePreview"
              > 
                <img
                  :src="newImagePreview"
                  alt="Selected Image"
                  class="image-preview"
                >
                <button
                  type="button"
                  class="btn btn-info"
                  @click="uploadNewImage()"
                >
                  {{ store.state.strings.uploaddefaultimage }}
                </button>
              </div>
            </div>
          </div>
        </div>
          <userSearch />
      </div>
    </div>
    <div v-else>
      <div class="card border-primary mb-3">
        <div class="card-header bg-primary text-white">
          <h5 class="card-title mb-0">
            {{ store.state.strings.fromlearningtitel }}
          </h5>
        </div>
        <div style="padding: 1rem;">
          <h4>{{ goalname }}</h4>
          <span v-if="goalname">
            <button
              type="button"
              class="btn btn-outline-primary btn-sm"
              :title="store.state.strings.charthelper_go_to_learningpath"
              @click.prevent="editLearningpath(props.goal.id)"
            >
              {{ store.state.strings.modals_edit}}
            </button>
          </span>
          <span v-else>
            {{ store.state.strings.charthelper_no_name }}
          </span>
        </div>
      </div>
      <div
        class="card border-secondary"
      >
        <div class="card-header bg-secondary text-white">
          <h5 class="card-title mb-0">
            {{ store.state.strings.fromlearningdescription }}
          </h5>
        </div>
        <div style="padding: 1rem;">
          <p class="card-text">
            {{ goaldescription ? goaldescription : store.state.strings.charthelper_no_name }}
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>

.required-sign {
  font-size: 1rem;
}

.image-option-img {
  height: 5rem;
  margin: 5px;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  cursor: pointer;
  transition: transform 0.2s ease-in-out;
}

.image-option {
  margin: 5px;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  cursor: pointer;
  transition: transform 0.2s ease-in-out;
}

.image-preview:hover, .image-option:hover {
  transform: scale(1.05);
}

.image-selection-container {
  display: flex;
  flex-wrap: wrap;
  justify-content: start;
  padding-top: 10px;
}

.image-preview {
  height: 7rem;
  margin: 5px;
  border-radius: 8px;
  display: inline-block;
  margin-right: 10px;
}

/* Style for the deselect button next to the preview image */
.deselect-btn {
  margin-left: 10px;
  cursor: pointer;
  color: #007bff;
  border: none;
  background: none;
}

.form-group img {
  height: 50px; /* Adjust the size of images in the form group */
}

</style>