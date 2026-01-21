<script setup>
  import { useStore } from 'vuex';
  const store = useStore()
  const props = defineProps({
      parentNodes: {
        type: Array,
        default: null,
      },
  });
</script>

<template>
  <div class="card-body">
    <h5 class="card-title">
      <i class="fas fa-arrow-circle-up" /> {{ store.state.strings.charthelper_parent_nodes }}
    </h5>
    <ul class="list-group list-group-flush">
      <template v-if="props.parentNodes.length > 0">
        <div 
          v-for="parent in props.parentNodes"
          :key="parent.data.fullname" 
        >
          <li class="list-group-item">
            {{ parent.data.fullname }}
          </li>
        </div>
      </template>
      <template v-else>
        <li class="list-group-item">
          {{ store.state.strings.charthelper_no_parent_nodes }}
        </li>
      </template>
    </ul>
  </div>
</template>
