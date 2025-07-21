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
      id="nodeModal"
      tabindex="-1"
      role="dialog"
      class="modal fade"
      aria-labelledby="exampleModalLabel"
      aria-hidden="true"
    >
      <div
        class="modal-dialog modal-lg"
        role="document"
      >
        <div class="modal-content">
          <div class="modal-header bg-primary text-white">
            <h5
              id="exampleModalLabel"
              class="modal-title"
            >
              {{ store.state.strings.modals_edit }} {{ fullname }}
            </h5>
            <button
              type="button"
              class="close text-white"
              data-dismiss="modal"
              aria-label="Close"
            >
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <div class="form-group">
              <label for="fullname">
                <b>
                  {{ store.state.strings.modals_longname }}
                </b>
              </label>
              <input
                id="fullname"
                v-model="fullname"
                class="form-control"
                type="text"
              >
            </div>
            <div class="form-group">
              <label for="description">
                <b>
                  {{ store.state.strings.modals_description }}
                </b>
              </label>
              <textarea
                id="description"
                v-model="description"
                class="form-control"
                :placeholder="store.state.strings.modals_no_description"
                rows="5"
              />
            </div>
            <div class="form-group">
              <label for="estimate_duration">
                <b>
                  {{ store.state.strings.estimate_duration }}
                  <i class="fa fa-spinner" />
                  :
                </b>
              </label>
              <input
                id="estimate_duration"
                v-model="estimate_duration"
                class="form-control"
                type="text"
              >
            </div>
            <div class="form-group">
              <b>
                {{ store.state.strings.modals_shortname }}
              </b>
              <p class="form-control-static">
                {{ shortname }}
              </p>
            </div>
            <div class="form-group">
              <b>{{store.state.strings.modals_tags}}<i class="fa fa-tag" /> :</b>
              <p class="form-control-static">
                {{ tags }}
              </p>
            </div>
            <div>
              <b>{{store.state.strings.modals_backgroundimage}}
                <i
                  :class="store.state.version ? 'fa-solid fa-image' : 'fa fa-picture-o'"
                />
                :</b>
              <p>
                {{store.state.strings.modals_select_stock_image_description}}
              </p>
              <div
                v-if="store.state.node && store.state.node.imagepaths && Object.keys(store.state.node.imagepaths).length > 0"
                class="mb-2"
              >
                <p>
                 {{store.state.strings.modals_select_course_image_description}}
                </p>
                <button
                  type="button"
                  class="btn btn-info"
                  @click="showCourseImageSelection = !showCourseImageSelection"
                >
                  {{store.state.strings.modals_select_button}}
                </button>
                <div
                  v-if="selectedCourseImagePath"
                  class="image-preview-container"
                >
                  <img
                    :src="selectedCourseImagePath"
                    alt="Selected Image"
                    class="image-preview"
                  >
                  <button
                    class="deselect-btn"
                    @click="selectedCourseImagePath = ''"
                  >
                  {{store.state.strings.modals_deselect_button}}
                  </button>
                </div>
                <div
                  v-if="showCourseImageSelection"
                  class="image-selection-container"
                >
                  <div
                    v-for="path in store.state.node.imagepaths"
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
              <button
                type="button"
                class="btn btn-info"
                @click="showImageSelection = !showImageSelection"
              >
                {{store.state.strings.modals_select_stock_image}}
              </button>
              <div
                v-if="selectedImagePath"
                class="image-preview-container"
              >
                <img
                  :src="selectedImagePath"
                  alt="Selected Image"
                  class="image-preview"
                >
                <button @click="selectedImagePath = ''" class="deselect-btn">Deselect</button>
              </div>
              <div v-if="showImageSelection" class="image-selection-container">
                <div v-for="path in imagePaths" :key="path" class="image-option" @click="selectImage(path.path)">
                  <img :src="path.path" alt="Image" class="image-option-img">
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button
              type="button"
              class="btn btn-secondary"
              data-dismiss="modal"
            >
              {{ store.state.strings.modals_close }}
            </button>
            <button
              type="button"
              class="btn btn-primary"
              data-dismiss="modal"
              @click="saveChanges"
            >
              {{ store.state.strings.modals_save_changes }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
// import dependancies
import { useStore } from 'vuex'
import { onMounted, ref, watch } from 'vue';

// define constants
const store = useStore();
const learningpathModal = ref(null);
const fullname = ref('')
const description = ref('')
const estimate_duration = ref('')
const shortname = ref('')
const tags = ref('')
const node_id = ref('')

const props = defineProps({
  learningpath: {
    type: Object,
    required: true,
  }
});

// States for image selection
const showImageSelection = ref(false)
const selectedImagePath = ref('')
const showCourseImageSelection = ref(false)
const selectedCourseImagePath = ref('')
const imagePaths = ref({})

const selectImage = (path) => {
  selectedImagePath.value = path;
  showImageSelection.value = false;
};

const selectCourseImage = (path) => {
  selectedCourseImagePath.value = path;
  showCourseImageSelection.value = false;
};

onMounted( async () => {
  if (store.state.lpimages.helpingslider) {
    imagePaths.value = store.state.lpimages.node_background_image;
  }
  learningpathModal.value = props.learningpath
})

const emit = defineEmits([
  'save-edit',
]);

// updating changes and closing modal
const saveChanges = () => {
  store.commit('updatedNode', {
    fullname: fullname.value,
    description: description.value,
    estimate_duration: estimate_duration.value,
    shortname: shortname.value,
    selected_image: selectedImagePath.value,
    selected_course_image: selectedCourseImagePath.value,
    node_id: node_id.value,
  })
  emit('save-edit', {
    fullname: fullname.value,
    description: description.value,
    estimate_duration: estimate_duration.value,
    selected_image: selectedImagePath.value,
    selected_course_image: selectedCourseImagePath.value,
    node_id: node_id.value,
  });
}

// watch values from selected node
watch(() => store.state.node, (newValue) => {
  fullname.value = newValue.fullname
  description.value = newValue.description
  estimate_duration.value = newValue.estimate_duration
  shortname.value = newValue.shortname
  tags.value = newValue.tags
  selectedImagePath.value = newValue.selected_image
  selectedCourseImagePath.value = newValue.selected_course_image
  node_id.value = newValue.node_id
});

</script>

<style scoped>

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
