<script setup>
import { onMounted, onUnmounted } from 'vue';
import { useVueFlow } from '@vue-flow/core'


// load useVueFlow properties / functions
const { findNode, removeNodes, addNodes } = useVueFlow()

const props = defineProps({
  data: {
    type: Object,
    required: true,
  },
});

const emit = defineEmits([
  'done-folding'
]);

onMounted(() => {
  const expandedNode = findNode(props.data.node_id)
  if (!expandedNode) return

  props.data.course_node_id.forEach((node_id, index) => {
    // Copy the position object to avoid modifying the original node's position
    const position = {
      x: expandedNode.position.x + (index + 1) * 500,
      y: expandedNode.position.y,
    }
    const nodeData = JSON.parse(JSON.stringify(expandedNode.data))
    nodeData.course_id = node_id // Add course_id to the copied data
    nodeData.showCard = false

    const newnode = {
      id: `${props.data.node_id}_expanded_courses_${node_id}`,
      type: 'expandedcourses',
      data: nodeData,
      draggable: false,
      deletable: false,
      position,
    }
    setTimeout(() => {
      addNodes([newnode])
      if (index === props.data.course_node_id.length - 1) {
        emit('done-folding');
      }
    }, index * 300)
  })
})

onUnmounted(() => {
  let remove_nodes = []
  let reversed_course_node_id = props.data.course_node_id;
  reversed_course_node_id = reversed_course_node_id.reverse();
  reversed_course_node_id.forEach((node_id, index) => {
    remove_nodes.push(props.data.node_id + '_expanded_courses_' + node_id)
    let remove_node =findNode(props.data.node_id + '_expanded_courses_' + node_id)
    if (remove_node) {
      remove_node.deletable = true
      setTimeout(() => {
        remove_node.data.showCard = false
      }, index * 300)
      setTimeout(() => {
        removeNodes([remove_node])
      }, (index+1) * 300)
      if (index === props.data.course_node_id.length - 1) {
          emit('done-folding');
        }
    }
  })
})
</script>

<style scoped>

</style>
