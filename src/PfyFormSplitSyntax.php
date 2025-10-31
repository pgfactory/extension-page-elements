<?php

namespace PgFactory\PageFactory;


 // === class PfyFormSplitSyntax ======================================
class PfyFormSplitSyntax extends PfyForm
{
    private mixed $lastRendered = false;

    /**
     * Prerequisite: createForm() executed -> form structure ($this->formElements) is set up at this point.
     * Now render elements in chunks (defined by the name of the last element to render)
     * Keeps track via $this->lastRendered what elements have been rendered before.
     * @param string|bool $uptoWhich
     * @return string
     * @throws \Exception
     */
    public function renderFormPieces(string|bool $uptoWhich): string
    {
        $html = '';
        // formResponse is set by received data eval
        // -> can be
        //      error in data
        //      deadline expired
        //      maxCount exceeded

        // lastRendered:
        //   = false: this is the very first run -> render form head
        //   = true:  all elements have been rendered -> render form tail
        //   = int:   index of next element to be rendered


        if ($this->lastRendered === false) {
            $this->lastRendered = 0;
            if (!$this->showForm) {
                return '';
            }
            $html = $this->renderFormHead();
            $i = 0; // render elem 0 next

        } else {
            $i = $this->lastRendered + 1;
        }

        $names = array_map(function($e) {
            return $e['name'];
        }, $this->formElements);
        $names = array_values($names);
        $lastElemInx = sizeof($names) - 1;

        // determine which pieces to render next:
        //      from = $i  to = $upTo
        if ($uptoWhich === 'head') {
            $upTo = -1;
            $this->lastRendered = -1;

        } elseif ($uptoWhich === true || $uptoWhich === 'rest') {
            $upTo = $lastElemInx;
            $this->lastRendered = true;

        } else if ($uptoWhich === 'tail') {
            $upTo = -1;
            $this->lastRendered = -1;

        } else {
            $upTo = array_search($uptoWhich, $names);
            if ($upTo === false) {
                exit("Split-Form element unknown: '$uptoWhich'");
            }
            $this->lastRendered = ($upTo === $lastElemInx)? true: $upTo;
        }

        // render elements of specified piece:  from $i to $upTo
        if ($this->showForm) {
            for (; $i <= $upTo; $i++) {
                if (!isset($names[$i])) {
                    throw new \Exception("Split-Form element unknown: '$uptoWhich'");
                }
                $name = $names[$i];
                $html .= $this->renderFormElement($name);
            }
        }

        if ($uptoWhich === 'rest') {
            $html .= $this->renderFormButtons();
        }

        // render everything after the last form element:
        if ($uptoWhich === 'tail') {
            if ($this->showForm) {
                $html .= $this->renderFormTail();               //        /pfy-elems-wrapper
                                                                //      /form
                                                                //    /pfy-form-wrapper
                $html .= $this->renderDataTable();              //    pfy-table-data-output-wrapper/
                $html .= $this->renderFormTableWrapperTail();   // /pfy-form-and-table-wrapper
                $html .= $this->renderProblemWithFormBanner();  // pfy-problem-with-form-hint/
            }

            $html .= $this->injectNoSHowEnd();
            $html .= "<!-- === /pfy form widget === -->\n\n";
        }

        return $html;
    } // renderFormPieces


} // PfyFormSplitSyntax