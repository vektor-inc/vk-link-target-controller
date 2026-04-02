/**
 * VK Link Target Controller - Block Editor Panel
 * ブロックエディタ用サイドバーパネル
 *
 * Replaces the legacy add_meta_box() with PluginDocumentSettingPanel
 * so that WordPress 7.0 RTC (Real-Time Collaboration) is not blocked.
 * レガシーなadd_meta_box()をPluginDocumentSettingPanelに置き換え、
 * WordPress 7.0 RTC（リアルタイム共同編集）がブロックされないようにする。
 */

import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { CheckboxControl, Button } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { __experimentalLinkControl as LinkControl } from '@wordpress/block-editor';
import { useState } from '@wordpress/element';

const VkLtcPanel = () => {
	const { postType, meta, candidatePostTypes } = useSelect( ( select ) => {
		const editor = select( 'core/editor' );
		return {
			postType: editor.getCurrentPostType(),
			meta: editor.getEditedPostAttribute( 'meta' ) || {},
			candidatePostTypes: window.vkLtcEditor?.postTypes || [],
		};
	}, [] );

	const { editPost } = useDispatch( 'core/editor' );

	// State for toggling the link search popover.
	// リンク検索ポップオーバーの表示切り替え用ステート
	const [ isLinkOpen, setIsLinkOpen ] = useState( false );

	// Only show panel for enabled post types.
	// 有効な投稿タイプでのみパネルを表示
	if ( ! candidatePostTypes.includes( postType ) ) {
		return null;
	}

	const link = meta[ 'vk-ltc-link' ] || '';
	const target = meta[ 'vk-ltc-target' ];
	const isTargetBlank = target === '1' || target === 1;

	/**
	 * Update a single meta key value.
	 * メタキーの値を更新する
	 *
	 * @param {string} key   Meta key name.
	 * @param {*}      value Meta value.
	 */
	const updateMeta = ( key, value ) => {
		editPost( { meta: { [ key ]: value } } );
	};

	/**
	 * Open the WordPress media uploader to select a file.
	 * WordPressメディアアップローダーを開いてファイルを選択する
	 */
	const openMediaUploader = () => {
		const uploader = wp.media( {
			title: __( 'Choose File', 'vk-link-target-controller' ),
			button: {
				text: __( 'Choose File', 'vk-link-target-controller' ),
			},
			multiple: false,
		} );
		uploader.on( 'select', () => {
			const file = uploader
				.state()
				.get( 'selection' )
				.first()
				.toJSON();
			updateMeta( 'vk-ltc-link', file.url );
		} );
		uploader.open();
	};

	return (
		<PluginDocumentSettingPanel
			name="vk-ltc-panel"
			title={ __( 'URL to redirect to', 'vk-link-target-controller' ) }
			className="vk-ltc-panel"
		>
			<p style={ { fontSize: '12px', color: '#757575' } }>
				{ __(
					'If you enter an URL here your visitors will access that URL directly when they click on the title of this post in Recent Posts list.',
					'vk-link-target-controller'
				) }
			</p>

			{ /* URL input with internal link search / 内部リンク検索付きURL入力 */ }
			{ isLinkOpen ? (
				<LinkControl
					value={ link ? { url: link } : undefined }
					settings={ [] }
					onChange={ ( nextValue ) => {
						updateMeta(
							'vk-ltc-link',
							nextValue?.url || ''
						);
						setIsLinkOpen( false );
					} }
					onRemove={ () => {
						updateMeta( 'vk-ltc-link', '' );
						setIsLinkOpen( false );
					} }
				/>
			) : (
				<div>
					{ link && (
						<div
							style={ {
								padding: '8px 12px',
								background: '#f0f0f0',
								borderRadius: '4px',
								marginBottom: '8px',
								overflow: 'hidden',
								textOverflow: 'ellipsis',
								whiteSpace: 'nowrap',
								fontSize: '12px',
							} }
						>
							<a
								href={ link }
								target="_blank"
								rel="noopener noreferrer"
							>
								{ link }
							</a>
						</div>
					) }
					<div
						style={ {
							display: 'flex',
							gap: '8px',
							flexWrap: 'wrap',
						} }
					>
						<Button
							variant="secondary"
							onClick={ () => setIsLinkOpen( true ) }
						>
							{ link
								? __(
										'Edit Link',
										'vk-link-target-controller'
								  )
								: __(
										'Set Link',
										'vk-link-target-controller'
								  ) }
						</Button>
						<Button
							variant="secondary"
							onClick={ openMediaUploader }
						>
							{ __(
								'File Link',
								'vk-link-target-controller'
							) }
						</Button>
						{ link && (
							<Button
								variant="tertiary"
								isDestructive
								onClick={ () =>
									updateMeta( 'vk-ltc-link', '' )
								}
							>
								{ __(
									'Remove',
									'vk-link-target-controller'
								) }
							</Button>
						) }
					</div>
				</div>
			) }

			<div style={ { marginTop: '16px' } }>
				<CheckboxControl
					label={ __(
						'Open the link in a separate window',
						'vk-link-target-controller'
					) }
					checked={ isTargetBlank }
					onChange={ ( checked ) =>
						updateMeta(
							'vk-ltc-target',
							checked ? '1' : '0'
						)
					}
				/>
			</div>
		</PluginDocumentSettingPanel>
	);
};

registerPlugin( 'vk-ltc-panel', {
	render: VkLtcPanel,
} );
