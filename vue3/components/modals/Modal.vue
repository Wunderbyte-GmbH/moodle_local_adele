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
    <div class="modal fade" id="nodeModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
          <div class="modal-header bg-primary text-white">
            <h5 class="modal-title" id="exampleModalLabel">Edit {{fullname}}</h5>
            <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" @click="closeModal">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <div class="form-group">
              <label for="fullname"><b>Longname:</b></label>
              <input
                type="text"
                class="form-control"
                id="fullname"
                v-model="fullname"
              />
            </div>
            <div class="form-group">
              <label for="shortname"><b>Shortname:</b></label>
              <p class="form-control-static">{{shortname}}</p>
            </div>
            <div class="form-group">
              <label for="tags"><b>Tags <i class="fa fa-tag"></i>:</b></label>
              <p class="form-control-static">{{tags}}</p>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal" @click="closeModal">Close</button>
            <button type="button" class="btn btn-primary" @click="saveChanges">Save Changes</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
// import dependancies
import { useStore } from 'vuex'
import { ref, watch } from 'vue';

// define constants
const store = useStore();
const fullname = ref('')
const shortname = ref('')
const tags = ref('')
const node_id = ref('')

// closing modal
const closeModal = () => {
  $('#nodeModal').modal('hide');
};

// updating changes and closing modal
const saveChanges = () => {
  store.commit('updatedNode', {
    fullname: fullname.value, 
    shortname: shortname.value,
    node_id: node_id.value,
  });
  $('#nodeModal').modal('hide');
};

// watch values from selected node
watch(() => store.state.node, (newValue, oldValue) => {
  fullname.value = newValue.fullname;
  shortname.value = newValue.shortname;
  tags.value = newValue.tags;
  node_id.value = newValue.node_id;
});

</script>