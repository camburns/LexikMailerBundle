<?php

namespace Lexik\Bundle\MailerBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

/**
 * Email controller.
 *
 * @author Laurent Heurtault <l.heurtault@lexik.fr>
 * @author Yoann Aparici <y.aparici@lexik.fr>
 */
class EmailController extends Controller
{
    /**
     * List all emails
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function listAction()
    {
        $em = $this->get('doctrine.orm.entity_manager');
        $emails = $em->getRepository($this->container->getParameter('lexik_mailer.email_entity.class'))->findAll();

        return $this->render('LexikMailerBundle:Email:list.html.twig', array_merge(array(
            'emails' => $emails,
            'layout' => $this->container->getParameter('lexik_mailer.base_layout'),
            'locale' => $this->container->getParameter('locale'),
        ), $this->getAdditionalParameters()));
    }

    /**
     * Email edition
     *
     * @param Request $request
     * @param string  $emailId
     * @param string  $lang
     *
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function editAction(Request $request, $emailId, $lang = null)
    {
        $class = $this->container->getParameter('lexik_mailer.email_entity.class');
        $email = $this->get('doctrine.orm.entity_manager')->find($class, $emailId);

        if (!$email) {
            throw $this->createNotFoundException(sprintf('No email found for id "%d"', $emailId));
        }

        $handler = $this->get('lexik_mailer.form.handler.email');
        $form = $handler->createForm($email, $lang);

        if ($handler->processForm($form, $request)) {
            return $this->redirect($this->generateUrl('lexik_mailer.email_edit', array(
                'emailId' => $email->getId(),
                'lang'    => $handler->getLocale(),
            )));
        }

        return $this->render('LexikMailerBundle:Email:edit.html.twig', array_merge(array(
            'form'          => $form->createView(),
            'layout'        => $this->container->getParameter('lexik_mailer.base_layout'),
            'email'         => $email,
            'lang'          => $handler->getLocale(),
            'displayLang'   => \Locale::getDisplayLanguage($handler->getLocale()),
            'routePattern'  => urldecode($this->generateUrl('lexik_mailer.email_edit', array('emailId' => $email->getId(), 'lang' => '%lang%'), true)),
        ), $this->getAdditionalParameters()));
    }

    /**
     * Delete email
     *
     * @param $emailId
     *
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function deleteAction($emailId)
    {
        $class = $this->container->getParameter('lexik_mailer.email_entity.class');

        $em = $this->get('doctrine.orm.entity_manager');
        $email = $em->find($class, $emailId);

        if (!$email) {
            throw $this->createNotFoundException(sprintf('No email found for id "%d"', $emailId));
        }

        $em->remove($email);
        $em->flush();

        return $this->redirect($this->generateUrl('lexik_mailer.email_list'));
    }

    /**
     * New email
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function newAction(Request $request)
    {
        $handler = $this->get('lexik_mailer.form.handler.email');
        $form = $handler->createForm();

        if ($handler->processForm($form, $request)) {
            return $this->redirect($this->generateUrl('lexik_mailer.email_list'));
        }

        return $this->render('LexikMailerBundle:Email:new.html.twig', array_merge(array(
            'form'      => $form->createView(),
            'layout'    => $this->container->getParameter('lexik_mailer.base_layout'),
            'lang'      => \Locale::getDisplayLanguage($handler->getLocale()),
        ), $this->getAdditionalParameters()));
    }

    /**
     * Preview an email
     *
     * @param int $emailId
     * @param     $lang
     *
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function previewAction($emailId, $lang)
    {
        $em = $this->get('doctrine.orm.entity_manager');

        $class = $this->container->getParameter('lexik_mailer.email_entity.class');
        $email = $em->find($class, $emailId);

        if (!$email) {
            throw $this->createNotFoundException(sprintf('No email found for id "%d"', $emailId));
        }

        $email->setLocale($lang);

        $renderer = $this->get('lexik_mailer.message_renderer');
        $renderer->loadTemplates($email);
        $renderer->setStrictVariables(false);

        $subject = $email->getSubject();
        $fromName = $email->getFromName($this->container->getParameter('lexik_mailer.admin_email'));
        $content = $email->getBody();

        $errors = array(
            'subject'      => null,
            'from_name'    => null,
            'html_content' => null,
        );

        $suffix = $email->getChecksum();
        foreach ($errors as $template => $error) {
            try {
                $renderer->renderTemplate(sprintf('%s_%s', $template, $suffix));
            } catch(\Twig_Error $e) {
                $errors[$template] = $e->getRawMessage();
            }
        }

        $renderer->setStrictVariables(true);

        return $this->render('LexikMailerBundle:Email:preview.html.twig', array_merge(array(
            'content'  => $content,
            'subject'  => $subject,
            'fromName' => $fromName,
            'errors'   => $errors,
        ), $this->getAdditionalParameters()));
    }

    /**
     * Delete a translation
     *
     * @param string $translationId
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function deleteTranslationAction($translationId)
    {
        $em = $this->get('doctrine.orm.entity_manager');
        $translation = $em->find('LexikMailerBundle:EmailTranslation', $translationId);

        if (!$translation) {
            throw $this->createNotFoundException(sprintf('No translation found for id "%d"', $translationId));
        }

        $em->remove($translation);
        $em->flush();

        return $this->redirect($this->generateUrl('lexik_mailer.email_edit', array('emailId' => $translation->getEmail()->getId())));
    }

    /**
     * Return some additional parameters to pass to the view.
     *
     * @return array
     */
    protected function getAdditionalParameters()
    {
        return array();
    }
}
