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
              <b>
                {{ store.state.strings.modals_shortname }}
              </b>
              <p class="form-control-static">
                {{ shortname }}
              </p>
            </div>
            <div class="form-group">
              <b>Tags <i class="fa fa-tag" /> :</b>
              <p class="form-control-static">
                {{ tags }}
              </p>
            </div>
            <div>
              <b>Background image <i class="fa-solid fa-image" /> :</b>
              <p>
                If no course image is available, the selected stock image will be choosen.
              </p>
              <div 
                v-if="store.state.node && Object.keys(store.state.node.imagepaths).length == 1"
                class="form-control-static"
              >
                <p>
                  If no image is selected, the course image will be selected.
                </p>
              </div>
              <div 
                v-if="store.state.node && Object.keys(store.state.node.imagepaths).length > 1"
                class="mb-2"
              >
                <p>
                  Multiple courseimages detected. Please select one image
                </p>
                <button 
                  type="button" 
                  class="btn btn-info" 
                  @click="showCourseImageSelection = !showCourseImageSelection"
                >
                  Select course image
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
                    Deselect
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
              <button type="button" class="btn btn-info" @click="showImageSelection = !showImageSelection">
                Select Stock Image
              </button>
              <div v-if="selectedImagePath" class="image-preview-container">
                <img :src="selectedImagePath" alt="Selected Image" class="image-preview">
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
const shortname = ref('')
const tags = ref('')
const node_id = ref('')

const props = defineProps({
  learningpath: {
    type: Array,
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
  imagePaths.value = await store.dispatch('fetchImagePaths', {
    path: 'node_background_image'
  });
  learningpathModal.value = props.learningpath
})

const emit = defineEmits([
  'save-edit',
]);

// updating changes and closing modal
const saveChanges = () => {
  learningpathModal.value.json.tree.nodes.forEach((node) => {
    if(node.id == store.state.node.node_id){
      node.data.fullname = fullname.value
      node.data.selected_course_image = selectedCourseImagePath.value
      node.data.selected_image = selectedImagePath.value
    }
  })
  store.commit('updatedNode', {
    fullname: fullname.value, 
    shortname: shortname.value,
    selected_image: selectedImagePath.value,
    selected_course_image: selectedCourseImagePath.value,
    node_id: node_id.value,
  })
  emit('save-edit', {
    fullname: fullname.value,
    selected_image: selectedImagePath.value,
    selected_course_image: selectedCourseImagePath.value,
    node_id: node_id.value,
  });
}

// watch values from selected node
watch(() => store.state.node, (newValue) => {
  fullname.value = newValue.fullname
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
