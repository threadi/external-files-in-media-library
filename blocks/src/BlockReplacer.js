/**
 * File to handle block replacement.
 */

import { store } from '@wordpress/block-editor'
import { createBlock } from '@wordpress/blocks'
import { useSelect, useDispatch } from '@wordpress/data'
import { useEffect } from '@wordpress/element'

/**
 * The BlockReplaced object.
 *
 * @param clientId
 * @param blockType
 * @param attributes
 * @constructor
 */
export const BlockReplacer = ({ clientId, blockType, attributes }) => {
    const block = useSelect(
        (select) => select(store).getBlock(clientId ?? ''),
        [clientId],
    )
    const { replaceBlock } = useDispatch(store)
    useEffect(() => {
        if (!block?.name || !replaceBlock || !clientId) return
        replaceBlock(clientId, [createBlock(blockType, attributes)])
    }, [block, replaceBlock, clientId, blockType])
}
