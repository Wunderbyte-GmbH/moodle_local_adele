<script setup>
import { onMounted, onUnmounted } from 'vue';
import { useStore } from 'vuex';
import { useVueFlow } from '@vue-flow/core'

const store = useStore();

// load useVueFlow properties / functions
const { findNode, removeNodes, addNodes } = useVueFlow()

const props = defineProps({
  data: {
    type: Object,
    required: true,
  },
});

onMounted(() => {
  const expandedNode = findNode(props.data.node_id)
  if (!expandedNode) return;

  const newNodes = props.data.course_node_id.map((node_id, index) => {
    // Copy the position object to avoid modifying the original node's position
    const position = { 
      x: expandedNode.position.x + (index + 1) * 500,
      y: expandedNode.position.y
    };
    const nodeData = JSON.parse(JSON.stringify(expandedNode.data));
    nodeData.course_id = node_id; // Add course_id to the copied data

    return {
      id: `${props.data.node_id}_expanded_courses_${node_id}`,
      type: 'expandedcourses',
      data: nodeData,
      draggable: false,
      deletable: false,
      position,
    };
  });
  addNodes(newNodes)
})

onUnmounted(() => {
  let remove_nodes = []
  props.data.course_node_id.forEach((node_id) => {
    remove_nodes.push(props.data.node_id + '_expanded_courses_' + node_id)
    let remove_node =findNode(props.data.node_id + '_expanded_courses_' + node_id)
    remove_node.deletable = true
  })
  removeNodes(remove_nodes)
})
</script>

<style scoped>

</style>
