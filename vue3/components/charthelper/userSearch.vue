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
 * user Search component.
 *
 * @package    local_adele
 * @copyright  2023 Wunderbyte GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */ -->

<script setup>
import { ref, watch, onMounted, onBeforeUnmount } from 'vue';
import { useStore } from 'vuex';
import { debounce } from 'lodash';
import { computed } from 'vue';

const store = useStore();

const searchQuery = ref('');
const foundUsers = ref([]);
const searchWarnings = ref('');
const selectedUsers = ref([]);
const isListVisible = ref(false);

const debouncedSearchUser = debounce(async () => {
  const result = await store.dispatch('getFoundUsers', searchQuery.value);
  foundUsers.value = result.list;
  searchWarnings.value = result.warnings;
  isListVisible.value = true;
}, 400);

watch(searchQuery, () => {
  debouncedSearchUser();
});

// The list shows what the server holds (#599): a change appears once the
// server has accepted it. A refusal (nopermissions, editorremoveowner on stale
// data ...) is reported by the store's ajax() and leaves the list as it was.
const addUser = async (user) => {
  isListVisible.value = false;
  try {
    await store.dispatch('createLpEditUsers', {
      lpid: store.state.learningPathID,
      userid: user.id,
    });
  } catch (e) {
    return;
  }
  if (!selectedUsers.value.some(selected => selected.id === user.id)) {
    selectedUsers.value.push(user);
  }
};

// Current owner of the path (delivered by get_learningpath, #488/#571).
const ownerId = computed(() => Number(store.state.learningpath && store.state.learningpath.ownerid) || 0);
const ownerName = computed(() => (store.state.learningpath && store.state.learningpath.ownername) || '');
const ownerLess = computed(() => !!(store.state.learningpath && store.state.learningpath.ownerless));
const isOwnerCard = (user) => Number(user.id) === ownerId.value;

// Hand the path to a new owner (Adele Manager only, #488).
const setOwner = async (user) => {
  const confirmation = confirm(
    (store.state.strings.setowner_confirm || '') + ' ' + user.firstname + ' ' + user.lastname
  );
  if (!confirmation) {
    return;
  }
  await store.dispatch('setLpOwner', {
    lpid: store.state.learningPathID,
    userid: user.id,
  });
  // Reflect the transfer immediately: golden crown + owner label flip.
  if (store.state.learningpath) {
    store.state.learningpath.ownerid = user.id;
    store.state.learningpath.ownername = user.firstname + ' ' + user.lastname;
    store.state.learningpath.ownerless = false;
  }
};

const isManager = computed(() => store.state.view == 'manager');

const removeUser = async (userId) => {
  const confirmation = confirm(store.state.strings.editordeleteconfirmation);
  if (
    !confirmation ||
    selectedUsers.value.length < 2
  ) {
    return;
  }
  try {
    await store.dispatch('removeLpEditUsers', {
      lpid: store.state.learningPathID,
      userid: userId,
    });
  } catch (e) {
    return;
  }
  selectedUsers.value = selectedUsers.value.filter(user => user.id !== userId);
};

// Hide the list if clicking outside of the input or list
const handleClickOutside = (event) => {
  const input = document.querySelector('.user-search-input');
  const list = document.querySelector('.user-list');
  if (input && list && !input.contains(event.target) && !list.contains(event.target)) {
    isListVisible.value = false;
  }
};

const isLearningPathIDZero = computed(() => store.state.learningPathID === 0);

const inputPlaceholder = computed(() =>
  isLearningPathIDZero.value ?
    store.state.strings.onlysetaftersaved :
    store.state.strings.searchuser
);

onMounted(async () => {
  selectedUsers.value = await store.dispatch('getLpEditUsers', store.state.learningPathID);
  document.addEventListener('click', handleClickOutside);
});

onBeforeUnmount(() => {
  document.removeEventListener('click', handleClickOutside);
});

</script>

<template>
  <div class="col-6">
    <h4>
      {{ store.state.strings.selectuser }}
      <small class="owner-label ml-2">
        <i class="fa fa-crown crown-owner" aria-hidden="true" />
        {{ store.state.strings.owner_label }}
        <b v-if="ownerName">{{ ownerName }}</b>
        <span v-else-if="ownerLess" class="badge badge-warning">
          {{ store.state.strings.main_ownerless }}
        </span>
      </small>
    </h4>
    <input
      v-model="searchQuery"
      class="form-control mb-2 user-search-input"
      :placeholder="inputPlaceholder"
      @focus="isListVisible = true"
      :disabled="isLearningPathIDZero"
    >
    <div v-if="isListVisible">
      <div v-if="searchWarnings" class="alert alert-warning">
        {{ searchWarnings }}
      </div>
      <div
        v-else-if="foundUsers.length > 0"
        class="user-list bg-white border rounded"
        style="max-height: 200px; overflow-y: auto;"
      >
        <div
          v-for="user in foundUsers"
          :key="user.id"
          class="user-item p-2"
          @click="addUser(user)"
          style="cursor: pointer;"
        >
          {{ user.firstname }} {{ user.lastname }}
        </div>
      </div>
      <div v-else class="alert alert-warning">
        {{ store.state.strings.nousersfound }}
      </div>
    </div>
    <div v-if="selectedUsers.length" class="d-flex flex-wrap mt-2">
      <div
        v-for="user in selectedUsers"
        :key="user.id"
        class="card card-user mb-2 mr-2"
      >
        <div class="card-body p-2 d-flex align-items-center justify-content-between">
          <span>{{ user.firstname }} {{ user.lastname }}</span>
          <i
            v-if="isOwnerCard(user)"
            class="fa fa-crown crown-owner ml-2 owner-crown"
            :title="store.state.strings.setowner_current"
            aria-hidden="true"
          />
          <button
            v-else-if="isManager"
            class="btn btn-link p-0 ml-2 setowner-btn"
            @click="setOwner(user)"
            :title="store.state.strings.setowner_button"
          >
            <i class="fa fa-crown crown-grey" aria-hidden="true" />
          </button>
          <!-- The owner always stays on the list (decision 2026-10-08):
               ownership is handed over with the crown, not by removal. -->
          <button
            v-if="selectedUsers.length > 1 && !isOwnerCard(user)"
            class="btn btn-link text-danger p-0"
            @click="removeUser(user.id)"
            :title="store.state.strings.removeuser"
          >
            &times;
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.crown-owner {
  color: #d4af37;
}
.crown-grey {
  color: #adb5bd;
}
.setowner-btn:hover .crown-grey {
  color: #d4af37;
}
.owner-label {
  font-size: 0.75em;
  color: #555;
}
.user-search-input {
  width: 100%;
}

.user-list {
  position: absolute;
  z-index: 1000;
  width: 100%;
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.user-item:hover {
  background-color: #f1f1f1;
}

.card-user {
  background-color: #f9f9f9;
  border: 1px solid #ddd;
  border-radius: 4px;
}

.card-body {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.card-user:hover {
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}
</style>
