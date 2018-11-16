/**
光兄的博客
LRU算法demo
*/
package main

import (
	"fmt"
)

//缓存长度
const CACHE_LEN = 4

//缓存数据
type Node struct {
	Key   int
	Value string
	Next  *Node
}

//缓存链表
type List struct {
	Front *Node
	Len   int //使用长度
	Free  int //空闲长度
}

//初始化链表
func initList() List {
	return List{
		Front: &Node{
			Key: -1,
		},
		Len:  0,
		Free: CACHE_LEN,
	}
}

func (list *List) set(key int, value string) {
	has, pre := (*list)._getNodePosition(key)
	//链表中是否已记录
	if has {
		node := (*pre).Next
		//改变值
		(*node).Value = value
		//将节点位置移动到第一位
		(*list)._moveNodeToFirst(pre)
	} else {
		node := &Node{key, value, nil}
		//是否是空链
		if (*((*list).Front)).Next != nil {
			first := (*list).Front
			second := (*first).Next
			(*first).Next = node
			(*node).Next = second
			//链表是否已满
			if (*list).Free == 0 {
				//寻找倒数第二个和第一个元素
				last := (*list).Front
				for (*last).Next != nil {
					last = (*last).Next
				}
				//删除最后一个
				last = nil
			}
		} else {
			(*((*list).Front)).Next = node
		}
	}
	if (*list).Free != 0 {
		(*list).Free--
		(*list).Len++
	}
}

func (list *List) get(key int) (string, bool) {
	node := (*list).Front
	//如果是空链表，返回nil
	if (*node).Next == nil {
		return "", false
	}
	for i := 0; i < (*list).Len; i++ {
		pre := node
		node = (*node).Next
		if (*node).Key == key {
			//移动节点到第一位
			(*list)._moveNodeToFirst(pre)
			return (*node).Value, true
		}
	}
	return "", false
}

func (list *List) del(key int) bool {
	node := (*list).Front
	//如果是空链表，返回失败
	if (*node).Next == nil {
		return false
	}
	for i := 0; i < (*list).Len; i++ {
		pre := node
		node = (*node).Next
		if (*node).Key == key {
			//删除节点
			(*pre).Next = (*node).Next
			//修改链表长度
			(*list).Len--
			(*list).Free++
			return true
		}
	}
	return false
}

/**
根据键获取数据节点的位置
因为使用的是单向链表，所以链表进行删除添加，只需要目标节点的前驱节点
该函数返回的是查找节点的前驱
*/
func (list *List) _getNodePosition(key int) (bool, *Node) {
	node := (*list).Front
	//如果是空链表，返回nil
	if node.Next == nil {
		return false, nil
	}
	var pre *Node
	for i := 0; i < (*list).Len; i++ {
		pre = node
		node = node.Next
		if (*node).Key == key {
			return true, pre
		}
	}
	return false, nil
}

/**
传入目标节点的前驱节点，
将目标节点移动至链表头
*/
func (list *List) _moveNodeToFirst(pre *Node) {
	node := (*pre).Next
	//改变链表位置,先将前驱指向当前后继
	(*pre).Next = node.Next
	//将当前节点插入第一个位置
	first := (*((*list).Front)).Next
	(*((*list).Front)).Next = node
	(*node).Next = first
}

func (list List) String() string {
	res := ""
	node := (*(list.Front)).Next
	for i := 0; i < list.Len; i++ {
		res += fmt.Sprintf("%d. key: %d, value: %s\n", i+1, (*node).Key, (*node).Value)
		node = (*node).Next
	}
	return res
}

func (node Node) String() string {
	return fmt.Sprintf("node{key: %d, value: %s}", node.Key, node.Value)
}
func main() {
	list := initList()
	list.set(1, "test1")
	list.set(2, "test2")
	list.set(3, "test3")
	list.set(4, "test4")
	list.set(5, "test5")
	fmt.Println(list)
	list.set(2, "test2.1")
	fmt.Println(list)
	list.set(3, "test3.1")
	fmt.Println(list)

	fmt.Println(list.get(2))
	fmt.Println(list)

	fmt.Println(list.del(5))
	fmt.Println(list)
}
