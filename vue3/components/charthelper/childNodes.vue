<script setup>
  import { useStore } from 'vuex';
  const store = useStore()

  // Import needed libraries
  const props = defineProps({
      childNodes: {
        type: Array,
        default: null,
      },
  });
</script>

<template>
  <div class="card-body">
    <h5 class="card-title">
      <i class="fa fa-arrow-circle-down" /> {{ store.state.strings.charthelper_child_nodes }}
    </h5>
    <ul class="list-group list-group-flush">
      <template v-if="props.childNodes.length > 0">
        <div 
          v-for="child in props.childNodes"
          :key="child.data.fullname" 
        >
          <li class="list-group-item">
            {{ child.data.fullname }}
          </li>
        </div>
      </template>
      <template v-else>
        <li class="list-group-item">
          {{ store.state.strings.charthelper_no_child_nodes }}
        </li>
      </template>
    </ul>
  </div>
</template>

