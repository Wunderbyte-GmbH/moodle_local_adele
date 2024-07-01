<script setup>
import { ref, watch, onMounted, inject } from 'vue';
import { notify } from "@kyvg/vue3-notification";

const props = defineProps({
  goal: {
      type: Object,
      default: null,
    },
});

// Load Store and Router
const store = inject('store');

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
  store.state.lpimages.forEach((lpimage) => {
    if (
      lpimage.path.includes('/local/adele') ||
      lpimage.path.includes('/uploaded_file_lp_' + store.state.learningPathID + '.')
    ) {
      selectionImages.value.push(lpimage.path)
    }
  })
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
  // '/local/adele/index.php#/learningpaths/edit/' +
  const tooltips = document.querySelectorAll('.tooltip');
  tooltips.forEach(tooltip => {
    tooltip.remove()
  });
  store.state.learningPathID = singlelearningpathid
  window.open('/local/adele/index.php#/learningpaths/edit/' + singlelearningpathid, '_blank');
};

</script>

<template>
  <div>
    <div v-if="store.state.view!='teacher'">
      <h4 class="font-weight-bold">
        {{ store.state.strings.fromlearningtitel }}
      </h4>
      <div class="mb-2">
        <input
          id="goalnameplaceholder"
          v-model="goalname"
          v-autowidth="{ maxWidth: '960px', minWidth: '20px', comfortZone: 0 }"
          class="form-control fancy-input"
          :placeholder="store.state.strings.goalnameplaceholder"
          type="text"
          autofocus
        >
      </div>
      <h4 class="font-weight-bold">
        {{ store.state.strings.fromlearningdescription }}
      </h4>
      <div class="mb-2">
        <textarea
          id="goalsubjectplaceholder"
          v-model="goaldescription"
          v-autowidth="{ maxWidth: '960px', minWidth: '40%', comfortZone: 0 }"
          class="form-control fancy-input"
          :placeholder="store.state.strings.goalsubjectplaceholder"
        />
      </div>
      <h4 class="font-weight-bold">
        {{ store.state.strings.from_default_node_image }}
      </h4>
      <div class="mb-2">
        Upload your default node image
        <div
          v-if="store.state.lpimages && Object.keys(store.state.lpimages).length > 0"
          class="mb-2"
        >
          <button
            type="button"
            class="btn btn-info"
            @click="showCourseImageSelection = !showCourseImageSelection"
          >
            Select learning path image
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
              @click="selectCourseImage()"
            >
              Deselect
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
          <label for="newImage">Or upload a new image:</label>
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
              Upload and use image
            </button>
          </div>
        </div>
      </div>
    </div>
    <div v-else>
      <div class="card border-primary mb-3">
        <div class="card-header bg-primary text-white">
          <h5 class="card-title mb-0">
            {{ store.state.strings.fromlearningtitel }}
          </h5>
        </div>
        <div class="card-body">
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
      <div class="card border-secondary">
        <div class="card-header bg-secondary text-white">
          <h5 class="card-title mb-0">
            {{ store.state.strings.fromlearningdescription }}
          </h5>
        </div>
        <div class="card-body">
          <p class="card-text">
            {{ goaldescription ? goaldescription : store.state.strings.charthelper_no_name }}
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

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