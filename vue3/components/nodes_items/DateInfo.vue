<template>
  <div>
    <div 
      v-if="date.start" 
      class="row mb-2"
    >
      <div class="col-4 text-left">
        <b>
          <i class="fas fa-calendar-alt mr-2" /> 
          {{ store.state.strings.nodes_items_start }}
        </b>
      </div>
      <div class="col-8 text-right">
        {{ formatDate(props.date.start) }}
      </div>
    </div>
    <div 
      v-if="date.end" 
      class="row mb-2"
    >
      <div class="col-4 text-left">
        <b>
          <i class="fas fa-calendar-alt mr-2" />
          {{ store.state.strings.nodes_items_end }}
        </b>
      </div>
      <div class="col-8 text-right">
        {{ formatDate(props.date.end) }}
      </div>
    </div>
  </div>
</template>

<script setup>
  import { useStore } from 'vuex';
  const store = useStore();

  const props = defineProps({
    date: {
      type: Object,
      required: true,
    },
  });

  const formatDate = (dateString) => {
    const options = { year: 'numeric', month: 'long', day: 'numeric', hour: 'numeric', minute: 'numeric', hour12: false };
    const formattedDate = new Date(dateString).toLocaleString('en-US', options);
    return formattedDate;
  }
</script>
