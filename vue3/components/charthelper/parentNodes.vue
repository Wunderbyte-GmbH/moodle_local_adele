<script setup>

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
      <i class="fa fa-arrow-circle-up" /> Parent Nodes:
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
          No parent nodes found.
        </li>
      </template>
    </ul>
  </div>
</template>
