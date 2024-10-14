
<script setup lang="ts">
import { onMounted, ref } from 'vue';

interface CompletionNode {
  priority: string;
}

interface Completion {
  completionnode: CompletionNode;
}

interface DataProps {
  completion: Completion;
}

const props = defineProps<{
  data: DataProps;
}>();

const medaille = ref<string | null>(null);

onMounted(() => {
  switch (props.data.completion.completionnode.priority) {
      case '1':
        medaille.value = '#FFD700'
        break;
      case '2':
        medaille.value = '#C0C0C0'
        break;
      default:
        medaille.value = '#CD7F32'
    }
})

</script>

<template>
  <div>
    <i
      :class="store.state.version ? 'fa-solid fa-award fa-2xl' : 'fa fa-trophy fa-2xl'"
      :style="{ color: medaille, textShadow: '0 0 2px #000' }"
    />
  </div>
</template>