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
      id="helpingSlider" 
      tabindex="-1" 
      role="dialog" 
      class="modal fade" 
      aria-labelledby="helpingSliderModal" 
      aria-hidden="true"
    >
      <div
        class="modal-dialog modal-lg modal-dialog-centered"
        role="document"
        style="max-width: 70% !important;"
      >
        <div class="modal-content">
          <div 
            class="modal-header text-white" 
            :style="{ backgroundColor: store.state.strings.DARK_GREEN }"
          >
            <h5 
              id="exampleModalLabel"
              class="modal-title" 
            >
              {{ store.state.strings.modals_how_to_learningpath }}
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
          <div 
            class="modal-body d-flex"
            :style="{ backgroundColor: store.state.strings.LIGHT_GRAY }"
          >
            <div 
              id="carouselExampleControls" 
              class="carousel slide" 
              data-ride="carousel" 
              data-interval="false"
            >
              <div class="carousel-inner helping-slide">
                <div 
                  v-for="(imagepath, index) in imagepaths" 
                  :key="index"
                  :class="{
                    'carousel-item': true,
                    'active': index === 0,
                  }"
                  style="height: 500px;"
                >
                  <div 
                    class="d-flex justify-content-center align-items-center w-100"
                    style="height: 500px;"
                  >
                    <img 
                      :src="imagepath.path" 
                      class="d-block w-100" 
                      :alt="'Slide ' + (index + 1)"
                    >
                  </div>
                </div>
              </div>
              <a 
                class="carousel-control-prev" 
                href="#carouselExampleControls" 
                role="button" 
                data-slide="prev"
                :style="{color: store.state.strings.DARK_GREEN}"
                style="width: 5%"
                @click="updateCarouselHeight"
              >
                <span class="slider-button">&lt;</span>
                <span class="sr-only">
                  {{ store.state.strings.modals_previous }}
                </span>
              </a>
              <a 
                class="carousel-control-next" 
                href="#carouselExampleControls" 
                role="button" 
                data-slide="next"
                :style="{color: store.state.strings.DARK_GREEN}"
                style="width: 5%"
                @click="updateCarouselHeight"
              >
                <span class="slider-button">
                  &gt;
                </span>
                <span class="sr-only">
                  {{ store.state.strings.modals_next }}
                </span>
              </a>
              <ol 
                class="carousel-indicators"
                :style="{backgroundColor: store.state.strings.DARK_GREEN}"
              >
                <li 
                  v-for="(imagepath, index) in imagepaths"
                  :key="index" 
                  data-target="#carouselExampleControls" 
                  :data-slide-to="index" 
                  :class="{ 'active': index === 0 }"
                />
              </ol>
            </div>
          </div>
          <div 
            class="modal-footer" 
            :style="{ backgroundColor: store.state.strings.DARK_GREEN }"
          >
            <button 
              type="button" 
              class="btn btn-secondary" 
              data-dismiss="modal"
            >
              {{ store.state.strings.modals_close }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
// import dependancies
import { onMounted, ref } from 'vue';
import { useStore } from 'vuex';

// Load Store 
const store = useStore();

const imagepaths = ref([])

onMounted(async () => {
  try {
    const response = await store.dispatch('fetchImagePaths', {
      path: 'helpingslider'
    });
    imagepaths.value = response; 
  } catch (error) {
    console.error('Error fetching image paths:', error);
  }
});


</script>

<style scoped>
.slider-button {
  font-weight: bold;
  font-size: xx-large;
}
.helping-slide {
  width: 90%;
  right: -5%;
  min-height: 500px;
}

</style>