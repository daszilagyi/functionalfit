import { useEditor, EditorContent } from '@tiptap/react'
import StarterKit from '@tiptap/starter-kit'
import Link from '@tiptap/extension-link'
import TextAlign from '@tiptap/extension-text-align'
import Underline from '@tiptap/extension-underline'
import { TextStyle } from '@tiptap/extension-text-style'
import { Color } from '@tiptap/extension-color'
import {
  Bold,
  Italic,
  Underline as UnderlineIcon,
  Strikethrough,
  Link2,
  Heading1,
  Heading2,
  Heading3,
  List,
  ListOrdered,
  AlignLeft,
  AlignCenter,
  AlignRight,
  Palette,
  RotateCcw,
  Type,
} from 'lucide-react'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'
import { useState, useEffect } from 'react'

interface TiptapEditorProps {
  value: string
  onChange: (value: string) => void
  placeholder?: string
  className?: string
}

export function TiptapEditor({ value, onChange, placeholder = '', className }: TiptapEditorProps) {
  const [showLinkInput, setShowLinkInput] = useState(false)
  const [linkUrl, setLinkUrl] = useState('')
  const [showColorPicker, setShowColorPicker] = useState(false)

  const editor = useEditor({
    extensions: [
      StarterKit.configure({
        heading: {
          levels: [1, 2, 3],
        },
        // Disable extensions that we're adding separately to avoid duplicates
        link: false,
      }),
      Link.configure({
        openOnClick: false,
        HTMLAttributes: {
          class: 'text-blue-600 underline',
        },
      }),
      TextAlign.configure({
        types: ['heading', 'paragraph'],
      }),
      Underline,
      TextStyle,
      Color,
    ],
    content: value,
    onUpdate: ({ editor }) => {
      onChange(editor.getHTML())
    },
    editorProps: {
      attributes: {
        class: 'prose prose-sm max-w-none focus:outline-none min-h-[300px] p-4',
      },
    },
  })

  // Update editor content when value prop changes externally
  useEffect(() => {
    if (editor && value !== editor.getHTML()) {
      editor.commands.setContent(value)
    }
  }, [value, editor])

  if (!editor) {
    return null
  }

  const handleSetLink = () => {
    if (linkUrl) {
      editor.chain().focus().setLink({ href: linkUrl }).run()
      setLinkUrl('')
      setShowLinkInput(false)
    }
  }

  const handleRemoveLink = () => {
    editor.chain().focus().unsetLink().run()
    setShowLinkInput(false)
  }

  const basicColors = [
    '#000000',
    '#DC2626',
    '#EA580C',
    '#D97706',
    '#65A30D',
    '#059669',
    '#0891B2',
    '#2563EB',
    '#7C3AED',
    '#C026D3',
  ]

  return (
    <div className={cn('border rounded-md overflow-hidden', className)}>
      {/* Toolbar */}
      <div className="border-b bg-gray-50 p-2 flex flex-wrap gap-1 sticky top-0 z-10">
        {/* Text Formatting */}
        <div className="flex gap-1 border-r pr-2">
          <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={() => editor.chain().focus().toggleBold().run()}
            className={cn(
              'h-8 w-8 p-0',
              editor.isActive('bold') && 'bg-gray-200'
            )}
            title="Bold (Ctrl+B)"
            aria-label="Toggle bold"
          >
            <Bold className="h-4 w-4" />
          </Button>
          <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={() => editor.chain().focus().toggleItalic().run()}
            className={cn(
              'h-8 w-8 p-0',
              editor.isActive('italic') && 'bg-gray-200'
            )}
            title="Italic (Ctrl+I)"
            aria-label="Toggle italic"
          >
            <Italic className="h-4 w-4" />
          </Button>
          <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={() => editor.chain().focus().toggleUnderline().run()}
            className={cn(
              'h-8 w-8 p-0',
              editor.isActive('underline') && 'bg-gray-200'
            )}
            title="Underline (Ctrl+U)"
            aria-label="Toggle underline"
          >
            <UnderlineIcon className="h-4 w-4" />
          </Button>
          <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={() => editor.chain().focus().toggleStrike().run()}
            className={cn(
              'h-8 w-8 p-0',
              editor.isActive('strike') && 'bg-gray-200'
            )}
            title="Strikethrough"
            aria-label="Toggle strikethrough"
          >
            <Strikethrough className="h-4 w-4" />
          </Button>
        </div>

        {/* Link */}
        <div className="flex gap-1 border-r pr-2">
          <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={() => setShowLinkInput(!showLinkInput)}
            className={cn(
              'h-8 w-8 p-0',
              editor.isActive('link') && 'bg-gray-200'
            )}
            title="Add link"
            aria-label="Add link"
          >
            <Link2 className="h-4 w-4" />
          </Button>
        </div>

        {/* Headings */}
        <div className="flex gap-1 border-r pr-2">
          <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={() => editor.chain().focus().toggleHeading({ level: 1 }).run()}
            className={cn(
              'h-8 w-8 p-0',
              editor.isActive('heading', { level: 1 }) && 'bg-gray-200'
            )}
            title="Heading 1"
            aria-label="Heading 1"
          >
            <Heading1 className="h-4 w-4" />
          </Button>
          <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={() => editor.chain().focus().toggleHeading({ level: 2 }).run()}
            className={cn(
              'h-8 w-8 p-0',
              editor.isActive('heading', { level: 2 }) && 'bg-gray-200'
            )}
            title="Heading 2"
            aria-label="Heading 2"
          >
            <Heading2 className="h-4 w-4" />
          </Button>
          <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={() => editor.chain().focus().toggleHeading({ level: 3 }).run()}
            className={cn(
              'h-8 w-8 p-0',
              editor.isActive('heading', { level: 3 }) && 'bg-gray-200'
            )}
            title="Heading 3"
            aria-label="Heading 3"
          >
            <Heading3 className="h-4 w-4" />
          </Button>
          <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={() => editor.chain().focus().setParagraph().run()}
            className={cn(
              'h-8 w-8 p-0',
              editor.isActive('paragraph') && 'bg-gray-200'
            )}
            title="Paragraph"
            aria-label="Paragraph"
          >
            <Type className="h-4 w-4" />
          </Button>
        </div>

        {/* Lists */}
        <div className="flex gap-1 border-r pr-2">
          <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={() => editor.chain().focus().toggleBulletList().run()}
            className={cn(
              'h-8 w-8 p-0',
              editor.isActive('bulletList') && 'bg-gray-200'
            )}
            title="Bullet list"
            aria-label="Bullet list"
          >
            <List className="h-4 w-4" />
          </Button>
          <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={() => editor.chain().focus().toggleOrderedList().run()}
            className={cn(
              'h-8 w-8 p-0',
              editor.isActive('orderedList') && 'bg-gray-200'
            )}
            title="Numbered list"
            aria-label="Numbered list"
          >
            <ListOrdered className="h-4 w-4" />
          </Button>
        </div>

        {/* Text Alignment */}
        <div className="flex gap-1 border-r pr-2">
          <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={() => editor.chain().focus().setTextAlign('left').run()}
            className={cn(
              'h-8 w-8 p-0',
              editor.isActive({ textAlign: 'left' }) && 'bg-gray-200'
            )}
            title="Align left"
            aria-label="Align left"
          >
            <AlignLeft className="h-4 w-4" />
          </Button>
          <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={() => editor.chain().focus().setTextAlign('center').run()}
            className={cn(
              'h-8 w-8 p-0',
              editor.isActive({ textAlign: 'center' }) && 'bg-gray-200'
            )}
            title="Align center"
            aria-label="Align center"
          >
            <AlignCenter className="h-4 w-4" />
          </Button>
          <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={() => editor.chain().focus().setTextAlign('right').run()}
            className={cn(
              'h-8 w-8 p-0',
              editor.isActive({ textAlign: 'right' }) && 'bg-gray-200'
            )}
            title="Align right"
            aria-label="Align right"
          >
            <AlignRight className="h-4 w-4" />
          </Button>
        </div>

        {/* Color */}
        <div className="flex gap-1 border-r pr-2">
          <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={() => setShowColorPicker(!showColorPicker)}
            className="h-8 w-8 p-0"
            title="Text color"
            aria-label="Text color"
          >
            <Palette className="h-4 w-4" />
          </Button>
        </div>

        {/* Clear Formatting */}
        <div className="flex gap-1">
          <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={() => editor.chain().focus().clearNodes().unsetAllMarks().run()}
            className="h-8 w-8 p-0"
            title="Clear formatting"
            aria-label="Clear formatting"
          >
            <RotateCcw className="h-4 w-4" />
          </Button>
        </div>
      </div>

      {/* Link Input */}
      {showLinkInput && (
        <div className="border-b bg-gray-50 p-2 flex gap-2 items-center">
          <input
            type="url"
            placeholder="https://example.com"
            value={linkUrl}
            onChange={(e) => setLinkUrl(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === 'Enter') {
                e.preventDefault()
                handleSetLink()
              }
              if (e.key === 'Escape') {
                setShowLinkInput(false)
                setLinkUrl('')
              }
            }}
            className="flex-1 px-2 py-1 text-sm border rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
            aria-label="Link URL"
            autoFocus
          />
          <Button
            type="button"
            size="sm"
            onClick={handleSetLink}
            className="h-7"
          >
            Set Link
          </Button>
          {editor.isActive('link') && (
            <Button
              type="button"
              size="sm"
              variant="outline"
              onClick={handleRemoveLink}
              className="h-7"
            >
              Remove
            </Button>
          )}
          <Button
            type="button"
            size="sm"
            variant="ghost"
            onClick={() => {
              setShowLinkInput(false)
              setLinkUrl('')
            }}
            className="h-7"
          >
            Cancel
          </Button>
        </div>
      )}

      {/* Color Picker */}
      {showColorPicker && (
        <div className="border-b bg-gray-50 p-2">
          <div className="flex gap-1">
            {basicColors.map((color) => (
              <button
                key={color}
                type="button"
                onClick={() => {
                  editor.chain().focus().setColor(color).run()
                  setShowColorPicker(false)
                }}
                className="w-6 h-6 rounded border-2 border-gray-300 hover:border-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
                style={{ backgroundColor: color }}
                title={color}
                aria-label={`Set color to ${color}`}
              />
            ))}
            <button
              type="button"
              onClick={() => {
                editor.chain().focus().unsetColor().run()
                setShowColorPicker(false)
              }}
              className="w-6 h-6 rounded border-2 border-gray-300 hover:border-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white relative"
              title="Reset color"
              aria-label="Reset color"
            >
              <span className="absolute inset-0 flex items-center justify-center text-red-500 text-xs font-bold">
                ×
              </span>
            </button>
          </div>
        </div>
      )}

      {/* Editor Content */}
      <div className="bg-white">
        <EditorContent
          editor={editor}
          className="tiptap-editor"
          data-placeholder={placeholder}
        />
      </div>
    </div>
  )
}
